<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقول جديدة لجدول الأصناف لربطها بمجموعات المنتجات
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // إضافة حقل المجموعة
            if (! Schema::hasColumn('categories', 'product_group_id')) {
                $table->unsignedBigInteger('product_group_id')->nullable()->after('id')->comment('مجموعة المنتجات');
            }
            
            // إضافة حقل الكود
            if (! Schema::hasColumn('categories', 'code')) {
                $table->string('code')->nullable()->after('name')->comment('كود الصنف');
            }
            
            // إضافة حقل حالة التفعيل
            if (! Schema::hasColumn('categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('discretion')->comment('حالة التفعيل');
            }

            // المفتاح الأجنبي
            if (! SchemaGuard::hasForeignKey('categories', 'categories_product_group_id_foreign')) {
                $table->foreign('product_group_id')
                    ->references('id')
                    ->on('product_groups')
                    ->onDelete('set null');
            }
            
            // فهارس للبحث السريع
            if (! SchemaGuard::hasIndex('categories', 'categories_product_group_id_index')) {
                $table->index('product_group_id');
            }
            if (! SchemaGuard::hasIndex('categories', 'categories_is_active_index')) {
                $table->index('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['product_group_id']);
            $table->dropColumn(['product_group_id', 'code', 'is_active']);
        });
    }
};
