<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collections_target_achievements')) {
            Schema::create('collections_target_achievements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('collections_target_id');
                $table->unsignedBigInteger('employee_id'); // users.id
                $table->unsignedBigInteger('treasury_id')->nullable();
                $table->decimal('achieved_amount', 15, 2)->default(0);
                $table->decimal('target_amount', 15, 2);
                $table->decimal('achievement_percentage', 5, 2)->default(0);
                $table->integer('payments_count')->default(0);
                $table->timestamp('last_payment_at')->nullable();
                $table->timestamps();

                $table->unique(['collections_target_id', 'employee_id'], 'uniq_coll_target_emp');
                $table->foreign('collections_target_id', 'fk_coll_ach_target')
                      ->references('id')->on('collections_targets')->cascadeOnDelete();
                $table->foreign('employee_id', 'fk_coll_ach_emp')->references('id')->on('users');
                $table->foreign('treasury_id', 'fk_coll_ach_treasury')->references('id')->on('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collections_target_achievements');
    }
};
