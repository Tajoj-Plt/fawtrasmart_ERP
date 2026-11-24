<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أصول/عهد الشركة الموضوعة لدى العميل (ثلاجات عرض، رفوف تبريد...) —
 * السجل الثابت للأصل نفسه؛ فحصه في كل زيارة مسجَّل في
 * `visit_asset_inspections` المنفصل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_company_assets')) {
            return;
        }

        Schema::create('client_company_assets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');

            $table->string('asset_code', 40); // مثال: A-2021-0142
            $table->string('asset_name');
            $table->string('category')->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('placed_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['client_id', 'is_active']);
            $table->unique(['client_id', 'asset_code']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_company_assets');
    }
};
