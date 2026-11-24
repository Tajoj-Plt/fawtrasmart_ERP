<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نظام خيارات المنتج ومتغيراته — تصميمٌ مُعاد اقتباسُه من قاعدة بيانات CRM
 * مرجعية (C:\Users\MetaCode\Downloads\crm.sql: product_options,
 * product_option_values, product_variants, variant_option_values)، لا
 * النظام القديم الذي حُذف في 2026_08_30_220000.
 *
 * الفرق الجوهري عن القديم: الخيار (product_options) مملوكٌ للمنتج مباشرةً
 * بعمود product_id — لا كتالوجٌ عامّ تُربط عناصره بجدول ربطٍ وسيط
 * (product_attribute_mappings سابقاً). فكل منتجٍ يعرّف خياراته بنفسه، ولا
 * حاجة لجدول ربطٍ خامس.
 *
 * `product_variants.variant_options` عمودٌ نصّي (JSON) يحمل لقطة القيم
 * المختارة للعرض المباشر بلا استعلام إضافي، بجانب `variant_option_values`
 * (بلا أعمدة زائدة) للاستعلام والفلترة الفعليين — نفس ازدواج الغاية في
 * الأصل المرجعي.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_options')) {
            Schema::create('product_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('name');
                $table->string('label_ar')->nullable();
                $table->string('label_en')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamp('created_at')->useCurrent();

                // منتجٌ لا يعرّف الخيار نفسه مرتين (مثال: "اللون" مرّتين لمنتج واحد)
                $table->unique(['product_id', 'name']);
            });
        }

        if (! Schema::hasTable('product_option_values')) {
            Schema::create('product_option_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('option_id')->constrained('product_options')->cascadeOnDelete();
                $table->string('value');
                $table->string('label_ar')->nullable();
                $table->string('label_en')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamp('created_at')->useCurrent();

                // خيارٌ لا تتكرّر قيمته نفسها مرّتين (مثال: "أحمر" مرّتين لخيار "اللون")
                $table->unique(['option_id', 'value']);
            });
        }

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('sku');
                $table->string('barcode')->nullable();
                // لقطة القيم المختارة {"اللون":"أحمر","المقاس":"L"} — للعرض المباشر
                $table->json('variant_options');
                $table->string('image_url')->nullable();
                $table->decimal('price_modifier', 10, 2)->default(0);
                $table->decimal('override_price', 12, 2)->nullable();
                $table->decimal('compare_at_price', 12, 2)->nullable();
                $table->decimal('cost_price', 12, 2)->nullable();
                $table->integer('stock_quantity')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();

                $table->index('product_id', 'idx_variants_product');
            });
        }

        // بلا id ولا طوابع زمنية ولا مفتاح فريد مركّب — نفس شكل جدول الربط
        // المرجعي تماماً (فهرسان بسيطان فقط، لا قيد تفرّد على الزوج)
        if (! Schema::hasTable('variant_option_values')) {
            Schema::create('variant_option_values', function (Blueprint $table) {
                $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->foreignId('option_value_id')->constrained('product_option_values')->cascadeOnDelete();

                $table->index('variant_id');
                $table->index('option_value_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
    }
};
