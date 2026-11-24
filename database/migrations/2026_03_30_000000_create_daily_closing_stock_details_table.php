<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_closing_stock_details')) {
            Schema::create('daily_closing_stock_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('daily_closing_id');
                $table->unsignedBigInteger('product_id');
                $table->string('product_name');
                $table->decimal('reported_quantity', 15, 2)->default(0);
                $table->decimal('system_quantity', 15, 2)->default(0);
                $table->timestamps();

                $table->foreign('daily_closing_id')->references('id')->on('daily_closings')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closing_stock_details');
    }
};
