<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول أهداف التحصيلات (مرآة sales_targets).
 *
 * يحسب التحصيلات الفعلية للموظف من:
 *   + الفواتير النقدية (treasury_id NOT NULL)
 *   + سندات القبض (receipts)
 *   - المرتجعات النقدية (invoice type=returned مع treasury_id)
 *   - الإشعارات الدائنة المرتبطة بفواتير نقدية
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collections_targets')) {
            Schema::create('collections_targets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('target_type', ['fixed', 'percentage'])->default('fixed');
                $table->decimal('target_value', 15, 2);
                $table->decimal('base_amount', 15, 2)->nullable();
                $table->decimal('commission_percentage', 5, 2)->default(0);
                $table->enum('commission_period', ['monthly', 'weekly'])->default('monthly');
                $table->boolean('auto_calculate_weekly')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['status', 'commission_period']);
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collections_targets');
    }
};
