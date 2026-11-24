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
        if (! Schema::hasTable('salary_advance_requests')) {
            Schema::create('salary_advance_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->decimal('amount', 10, 2);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->date('requested_date');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->datetime('approved_date')->nullable();
                $table->unsignedBigInteger('treasury_id')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                // Foreign Keys
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('treasury_id')->references('id')->on('accounts')->onDelete('set null');
            
                // Indexes
                $table->index('status');
                $table->index('requested_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_advance_requests');
    }
};
