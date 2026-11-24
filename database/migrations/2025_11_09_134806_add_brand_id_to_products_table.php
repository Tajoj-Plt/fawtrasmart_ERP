<?php

use App\Support\SchemaGuard;
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
        Schema::table('products', function (Blueprint $table) {
            // إضافة عمود brand_id وربطه بجدول brands
            if (! Schema::hasColumn('products', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('id');
            }

            // إضافة المفتاح الأجنبي إذا كان عندك جدول brands
            if (! SchemaGuard::hasForeignKey('products', 'products_brand_id_foreign')) {
                $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // حذف المفتاح الأجنبي والعمود عند التراجع
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });
    }
};
