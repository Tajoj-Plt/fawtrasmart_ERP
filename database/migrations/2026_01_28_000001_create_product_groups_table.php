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
        if (! Schema::hasTable('product_groups')) {
            Schema::create('product_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->comment('المجموعة الأب');
                $table->string('name')->comment('اسم المجموعة');
                $table->string('code')->nullable()->comment('كود المجموعة');
                $table->integer('level')->default(1)->comment('مستوى التفرع (1,2,3...)');
                $table->boolean('is_active')->default(true)->comment('حالة التفعيل');
                $table->timestamps();

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('product_groups')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_groups');
    }
};
