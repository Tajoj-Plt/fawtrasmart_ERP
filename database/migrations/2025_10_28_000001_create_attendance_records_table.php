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
        if (! Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table) {
                $table->id();
                // link back to the employees table, cascade on delete so orphaned records
                // are automatically removed if the employee is deleted
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                // optional session number for grouping multiple records on a given day
                $table->unsignedInteger('session_number')->nullable();
                // arbitrary string describing where the record originated (e.g. "manual", "barcode")
                $table->string('source')->nullable();
                // 0 = open, 1 = closed
                $table->boolean('status')->default(false);
                // when the employee checked in
                $table->dateTime('login_time')->nullable();
                // when the employee checked out
                $table->dateTime('logout_time')->nullable();

                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
