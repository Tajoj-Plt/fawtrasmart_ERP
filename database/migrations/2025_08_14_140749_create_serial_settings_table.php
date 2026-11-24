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
        if (! Schema::hasTable('serial_settings')) {
            Schema::create('serial_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('section')->unique()->comment('نوع القسم (invoice, customer, etc)');
                $table->integer('current_number')->default(0)->comment('الرقم الحالي');
                $table->integer('number_of_digits')->default(6)->comment('عدد خانات الرقم');
                $table->string('prefix', 10)->nullable()->comment('البادئة المضافة للرقم');
                $table->tinyInteger('mode')->default(0)->comment('نمط الترقيم (0-6)');
                $table->boolean('reset_yearly')->default(false)->comment('إعادة تعيين الترقيم سنوياً');
                $table->timestamps();

                $table->index('section');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serial_settings');
    }
};
