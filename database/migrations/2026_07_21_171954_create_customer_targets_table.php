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
        if (! Schema::hasTable('customer_targets')) {
            Schema::create('customer_targets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('color')->nullable();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->decimal('min_sales', 15, 2)->default(0)->comment('الحد الأدنى للمبيعات');
                $table->decimal('min_collections', 15, 2)->default(0)->comment('الحد الأدنى للتحصيلات');
                $table->decimal('min_collection_ratio', 5, 2)->default(0)->comment('الحد الأدنى لنسبة التحصيل');
                $table->integer('max_avg_collection_days')->default(0)->comment('الحد الأقصى لمتوسط أيام التحصيل');
                $table->decimal('max_late_ratio', 5, 2)->default(100)->comment('الحد الأقصى لنسبة التأخير');
                $table->decimal('max_debt', 15, 2)->default(0)->comment('الحد الأقصى للمديونية');
                $table->integer('min_invoices_count')->default(0)->comment('الحد الأدنى لعدد الفواتير');
                $table->integer('priority')->default(0)->comment('الأولوية عند تطابق الشروط');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_targets');
    }
};
