<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقول الوحدات ومعامل التحويل لجدول بنود الفواتير
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // الكمية بالوحدة الأساسية (بعد التحويل)
            if (!Schema::hasColumn('invoice_items', 'quantity_in_base_unit')) {
                $table->decimal('quantity_in_base_unit', 15, 4)->nullable()->after('quantity')
                    ->comment('الكمية بالوحدة الأساسية بعد التحويل');
            }
            
            // معامل التحويل المستخدم
            if (!Schema::hasColumn('invoice_items', 'conversion_factor')) {
                $table->decimal('conversion_factor', 10, 4)->default(1)->after('quantity_in_base_unit')
                    ->comment('معامل التحويل للوحدة المستخدمة');
            }
            
            // اسم الوحدة المستخدمة
            if (!Schema::hasColumn('invoice_items', 'unit_name')) {
                $table->string('unit_name', 100)->nullable()->after('conversion_factor')
                    ->comment('اسم الوحدة المستخدمة');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'quantity_in_base_unit')) {
                $table->dropColumn('quantity_in_base_unit');
            }
            if (Schema::hasColumn('invoice_items', 'conversion_factor')) {
                $table->dropColumn('conversion_factor');
            }
            if (Schema::hasColumn('invoice_items', 'unit_name')) {
                $table->dropColumn('unit_name');
            }
        });
    }
};
