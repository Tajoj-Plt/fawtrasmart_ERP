<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('sales_target_commission_tiers')) {
            Schema::create('sales_target_commission_tiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_target_id');
                $table->decimal('achievement_percentage', 5, 2)->comment('نسبة الإنجاز المطلوبة من الهدف');
                $table->decimal('commission_percentage', 5, 2)->comment('نسبة العمولة المستحقة');
                $table->integer('tier_order')->default(0)->comment('ترتيب المستوى');
                $table->timestamps();
            
                $table->foreign('sales_target_id')
                      ->references('id')
                      ->on('sales_targets')
                      ->onDelete('cascade');
                  
                $table->index(['sales_target_id', 'achievement_percentage'], 'st_comm_tiers_target_achieve_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('sales_target_commission_tiers');
    }
};
