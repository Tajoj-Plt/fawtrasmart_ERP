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
        if (! Schema::hasTable('client_stage_requests')) {
            Schema::create('client_stage_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('old_stage_id')->nullable();
                // «بلا وضع» هدفٌ صحيح — عميلٌ يُعتمد فيخرج من مراحل التسجيل
                // كلّها، فـnew_stage_id يقبل NULL خلافاً لـnew_status_id.
                $table->unsignedBigInteger('new_stage_id')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('old_stage_id')->references('id')->on('statuses')->onDelete('set null');
                $table->foreign('new_stage_id')->references('id')->on('statuses')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_stage_requests');
    }
};
