<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * إضافة حقول حساب النقاط المتقدمة:
     * - calculation_type: نوع الحساب (ثابت، نسبة، لكل مبلغ، متدرج)
     * - calculation_value: قيمة الحساب (النسبة أو المبلغ)
     * - min_amount: الحد الأدنى لقيمة المعاملة
     * - max_amount: الحد الأقصى لقيمة المعاملة
     * - max_points: سقف النقاط للمعاملة الواحدة
     * - tiers_config: JSON للمستويات المتدرجة
     */
    public function up(): void
    {
        Schema::table('points_settings', function (Blueprint $table) {
            // نوع الحساب: fixed (ثابت)، percentage (نسبة)، per_amount (لكل مبلغ)، tiered (متدرج)
            if (! Schema::hasColumn('points_settings', 'calculation_type')) {
                $table->enum('calculation_type', ['fixed', 'percentage', 'per_amount', 'tiered'])
                    ->default('fixed')
                    ->after('points')
                    ->comment('نوع حساب النقاط');
            }
            
            // قيمة الحساب (للنسبة: 1 = 1%، لكل مبلغ: 10 = نقطة لكل 10 ريال)
            if (! Schema::hasColumn('points_settings', 'calculation_value')) {
                $table->decimal('calculation_value', 10, 4)->nullable()->after('calculation_type')
                    ->comment('قيمة الحساب (نسبة أو مبلغ)');
            }
            
            // الحد الأدنى لقيمة المعاملة (اختياري)
            if (! Schema::hasColumn('points_settings', 'min_amount')) {
                $table->decimal('min_amount', 15, 2)->nullable()->after('calculation_value')
                    ->comment('الحد الأدنى لقيمة المعاملة');
            }
            
            // الحد الأقصى لقيمة المعاملة (اختياري)
            if (! Schema::hasColumn('points_settings', 'max_amount')) {
                $table->decimal('max_amount', 15, 2)->nullable()->after('min_amount')
                    ->comment('الحد الأقصى لقيمة المعاملة');
            }
            
            // سقف النقاط للمعاملة الواحدة (اختياري)
            if (! Schema::hasColumn('points_settings', 'max_points')) {
                $table->integer('max_points')->nullable()->after('max_amount')
                    ->comment('الحد الأقصى للنقاط الممنوحة');
            }
            
            // إعدادات المستويات المتدرجة (JSON)
            // مثال: [{"min": 0, "max": 100, "points": 5}, {"min": 100, "max": 500, "points": 15}]
            if (! Schema::hasColumn('points_settings', 'tiers_config')) {
                $table->json('tiers_config')->nullable()->after('max_points')
                    ->comment('إعدادات المستويات المتدرجة JSON');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_settings', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_type',
                'calculation_value',
                'min_amount',
                'max_amount',
                'max_points',
                'tiers_config',
            ]);
        });
    }
};
