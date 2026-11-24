<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقل الوحدة لعناصر الفاتورة
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // إضافة حقل معرف وحدة المنتج
            if (! Schema::hasColumn('invoice_items', 'product_unit_id')) {
                $table->unsignedBigInteger('product_unit_id')->nullable()->after('product_id');
            }
            
            // إضافة فهرس للحقل الجديد
            if (! SchemaGuard::hasIndex('invoice_items', 'invoice_items_product_unit_id_index')) {
                $table->index('product_unit_id', 'invoice_items_product_unit_id_index');
            }
            
            // إضافة علاقة مع جدول product_units
            if (! SchemaGuard::hasForeignKey('invoice_items', 'invoice_items_product_unit_fk')) {
                $table->foreign('product_unit_id', 'invoice_items_product_unit_fk')
                      ->references('id')
                      ->on('product_units')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // حذف العلاقة أولاً
            $table->dropForeign('invoice_items_product_unit_fk');
            
            // حذف الفهرس
            $table->dropIndex('invoice_items_product_unit_id_index');
            
            // حذف الحقل
            $table->dropColumn('product_unit_id');
        });
    }
};
