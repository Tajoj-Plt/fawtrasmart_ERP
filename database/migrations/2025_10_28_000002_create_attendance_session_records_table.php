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
        if (! Schema::hasTable('attendance_session_records')) {
            Schema::create('attendance_session_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_record_id');
                $table->unsignedInteger('session_number')->nullable();
                $table->string('source')->nullable();
                $table->boolean('status')->default(false);
                $table->dateTime('login_time');
                $table->dateTime('logout_time')->nullable();
                $table->timestamps();

                // Foreign key constraint ensures sessions are deleted when the parent record is deleted
                $table->foreign('attendance_record_id')
                    ->references('id')
                    ->on('attendance_records')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_session_records');
    }
};