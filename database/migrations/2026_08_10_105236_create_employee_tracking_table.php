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
        if (! Schema::hasTable('employee_tracking')) {
            Schema::create('employee_tracking', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->date('tracking_date')->index();
                $table->timestamps();
            
                $table->index(['employee_id', 'tracking_date']);
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_tracking');
    }
};
