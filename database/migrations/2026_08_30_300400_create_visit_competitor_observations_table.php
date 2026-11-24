<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** رصد المنافسين أثناء زيارة العميل — اسم المنافس، ملاحظة/سعر، وصورة. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_competitor_observations')) {
            return;
        }

        Schema::create('visit_competitor_observations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('visit_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('employee_id')->nullable();

            $table->string('competitor_name');
            $table->text('notes')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('photo_path')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->foreign('visit_id')->references('id')->on('visits')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_competitor_observations');
    }
};
