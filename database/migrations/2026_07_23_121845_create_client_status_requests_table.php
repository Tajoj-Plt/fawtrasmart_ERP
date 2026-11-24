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
        if (! Schema::hasTable('client_status_requests')) {
            Schema::create('client_status_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('old_status_id')->nullable();
                $table->unsignedBigInteger('new_status_id');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('old_status_id')->references('id')->on('statuses')->onDelete('set null');
                $table->foreign('new_status_id')->references('id')->on('statuses')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_status_requests');
    }
};
