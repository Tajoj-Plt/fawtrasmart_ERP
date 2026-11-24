<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * نظام متغيرات المنتجات (الألوان - المقاسات - الخصائص)
     */
    public function up(): void
    {
        // إصلاح ENUM المعطوب في جدول products أولاً
        // العمود status يحتوي على قيم مكررة فارغة enum('active', 'unactive', '', '')
        // الإصلاح خاصٌّ بـMySQL؛ SQLite (بيئة الاختبارات) لا يعرف MODIFY ولا
        // يعاني من العلّة أصلاً لأن الجدول يُبنى نظيفاً في كل مرة.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM('active', 'unactive') NOT NULL DEFAULT 'active'");
        }

        // 1. جدول الخصائص الرئيسية (مثل: اللون، المقاس، المادة)
        if (!Schema::hasTable('product_attributes')) {
            Schema::create('product_attributes', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // اسم الخاصية (اللون، المقاس)
                $table->string('name_en')->nullable(); // الاسم بالإنجليزية
                $table->string('type')->default('select'); // نوع العرض: select, color, button, image
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_visible')->default(true); // ظاهر للعميل
                $table->boolean('is_filterable')->default(true); // قابل للفلترة
                $table->timestamps();
            });
        }

        // 2. جدول قيم الخصائص (مثل: أحمر، أزرق، XL، L)
        if (!Schema::hasTable('product_attribute_values')) {
            Schema::create('product_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
                $table->string('value'); // القيمة (أحمر، XL)
                $table->string('value_en')->nullable();
                $table->string('color_code')->nullable(); // كود اللون للعرض (#FF0000)
                $table->string('image')->nullable(); // صورة للقيمة
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. جدول متغيرات المنتج (الـ Variants)
        if (!Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
                $table->string('sku')->nullable()->unique(); // كود المتغير الفريد
                $table->string('barcode')->nullable();
                $table->decimal('price', 15, 2)->nullable(); // سعر خاص للمتغير
                $table->decimal('cost_price', 15, 2)->nullable(); // تكلفة المتغير
                $table->decimal('price_adjustment', 15, 2)->default(0); // تعديل على السعر الأصلي
                $table->integer('quantity')->default(0); // الكمية
                $table->integer('low_stock_threshold')->nullable();
                $table->decimal('weight', 10, 3)->nullable();
                $table->json('images')->nullable(); // صور خاصة بالمتغير
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false); // المتغير الافتراضي
                $table->boolean('track_inventory')->default(true);
                $table->timestamps();
                
                $table->index(['product_id', 'is_active']);
            });
        }

        // 4. جدول ربط المتغيرات بقيم الخصائص (Pivot)
        if (!Schema::hasTable('product_variant_attributes')) {
            Schema::create('product_variant_attributes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
                $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->onDelete('cascade');
                $table->timestamps();
                
                $table->unique(['variant_id', 'attribute_value_id'], 'variant_attr_unique');
            });
        }

        // 5. جدول مخزون المتغيرات حسب المستودع
        if (!Schema::hasTable('product_variant_stock')) {
            Schema::create('product_variant_stock', function (Blueprint $table) {
                $table->id();
                $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
                $table->foreignId('storehouse_id')->constrained('store_houses')->onDelete('cascade');
                $table->integer('quantity')->default(0);
                $table->integer('reserved_quantity')->default(0); // الكمية المحجوزة
                $table->timestamps();
                
                $table->unique(['variant_id', 'storehouse_id']);
            });
        }

        // 6. جدول ربط المنتجات بالخصائص المتاحة
        if (!Schema::hasTable('product_attribute_mappings')) {
            Schema::create('product_attribute_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
                $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                
                $table->unique(['product_id', 'attribute_id']);
            });
        }

        // 7. إضافة أعمدة للمنتجات لدعم المتغيرات
        if (!Schema::hasColumn('products', 'has_variants')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'has_variants')) {
                    $table->boolean('has_variants')->default(false)->after('featured_product');
                }
                if (! Schema::hasColumn('products', 'variant_display_type')) {
                    $table->string('variant_display_type')->default('dropdown')->after('has_variants');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_variants', 'variant_display_type']);
        });

        Schema::dropIfExists('product_attribute_mappings');
        Schema::dropIfExists('product_variant_stock');
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
    }
};
