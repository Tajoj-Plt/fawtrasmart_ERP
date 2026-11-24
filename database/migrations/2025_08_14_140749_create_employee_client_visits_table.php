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
        if (! Schema::hasTable('employee_client_visits')) {
            Schema::create('employee_client_visits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->enum('day_of_week', ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
                $table->integer('year')->nullable();
                $table->string('justification')->nullable();
                $table->timestamp('justification_date')->nullable();
                $table->unsignedBigInteger('justification_approved')->nullable()->default(0); // 0 = pending, 1 = approved, 2 = rejected
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->enum('status', ['active', 'unactive'])->default('active');
                $table->integer('week_number')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_client_visits');
    }
};
