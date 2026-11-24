<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collections_target_commission_tiers')) {
            Schema::create('collections_target_commission_tiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('collections_target_id');
                $table->decimal('achievement_percentage', 5, 2);
                $table->decimal('commission_percentage', 5, 2);
                $table->integer('tier_order')->default(0);
                $table->timestamps();

                $table->index(['collections_target_id', 'achievement_percentage'], 'idx_coll_tier_lookup');
                $table->foreign('collections_target_id', 'fk_coll_tier_target')
                      ->references('id')->on('collections_targets')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collections_target_commission_tiers');
    }
};
