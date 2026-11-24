<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trusted_devices')) {
            Schema::create('trusted_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('device_name')->nullable();
                $table->string('device_fingerprint')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('browser')->nullable();
                $table->string('os')->nullable();
                $table->boolean('is_trusted')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'is_trusted']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
