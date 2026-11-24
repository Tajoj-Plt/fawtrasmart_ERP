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
        if (! Schema::hasTable('smtp_settings')) {
            Schema::create('smtp_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->boolean('use_smtp')->default(false);
                $table->string('sender_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->string('smtp_username')->nullable();
                $table->string('smtp_host')->nullable();
                $table->integer('smtp_port')->default(587);
                $table->string('smtp_encryption')->default('tls')->nullable();
                $table->boolean('ssl_required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_settings');
    }
};
