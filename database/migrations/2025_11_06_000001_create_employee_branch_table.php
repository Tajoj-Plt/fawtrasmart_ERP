<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores additional branch permissions for employees. Each record
     * associates an employee with a branch they are allowed to access beyond
     * their primary branch. A unique constraint prevents duplicate entries.
     */
    public function up(): void
    {
        if (! Schema::hasTable('employee_branch')) {
            Schema::create('employee_branch', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('branch_id');
                $table->timestamps();

                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->onDelete('cascade');
                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->onDelete('cascade');

                $table->unique(['employee_id', 'branch_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_branch');
    }
};