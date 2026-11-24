<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_module_settings')) {
            Schema::create('client_module_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('visits_enabled')->default(true);
                $table->boolean('itinerary_enabled')->default(true);
                $table->boolean('location_enabled')->default(true);
                $table->boolean('notes_enabled')->default(true);
                $table->boolean('appointments_enabled')->default(true);
                $table->boolean('crm_enabled')->default(true);
                $table->boolean('doubtful_debts_enabled')->default(true);
                $table->boolean('invoice_transfer_enabled')->default(true);
                $table->boolean('customer_targets_enabled')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_module_settings');
    }
};
