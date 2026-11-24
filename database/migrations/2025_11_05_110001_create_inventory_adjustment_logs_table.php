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
        if (! Schema::hasTable('inventory_adjustment_logs')) {
            Schema::create('inventory_adjustment_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('adjustment_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->enum('action', [
                    'created',           // إنشاء الجرد
                    'started',           // بدء عملية الجرد
                    'item_counted',      // تم جرد منتج
                    'completed',         // اكتمل الجرد
                    'adjusted',          // تمت التسوية
                    'cancelled',         // تم الإلغاء
                    'note_added'         // إضافة ملاحظة
                ]);
                $table->text('details')->nullable();
                $table->timestamps();

                $table->foreign('adjustment_id')
                      ->references('id')
                      ->on('inventory_adjustments')
                      ->onDelete('cascade');

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_logs');
    }
};
