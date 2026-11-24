<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تحسين أداء جداول نقاط البيع بإضافة Indexes
     */
    public function up(): void
    {
        // إضافة indexes لجدول products لتسريع البحث
        Schema::table('products', function (Blueprint $table) {
            // Index مركب للبحث السريع في POS
            if (! SchemaGuard::hasIndex('products', 'idx_products_pos_search')) {
                $table->index(['available_pos', 'status', 'category_id'], 'idx_products_pos_search');
            }
            
            // Index للبحث بالاسم
            if (! SchemaGuard::hasIndex('products', 'idx_products_name')) {
                $table->index('name', 'idx_products_name');
            }
            
            // Index للباركود
            if (! SchemaGuard::hasIndex('products', 'idx_products_barcode')) {
                $table->index('barcode', 'idx_products_barcode');
            }
            
            // Index لسعر البيع (للترتيب)
            if (! SchemaGuard::hasIndex('products', 'idx_products_sale_price')) {
                $table->index('sale_price', 'idx_products_sale_price');
            }
        });

        // إضافة indexes لجدول categories
        Schema::table('categories', function (Blueprint $table) {
            if (! SchemaGuard::hasIndex('categories', 'idx_categories_name')) {
                $table->index('name', 'idx_categories_name');
            }
        });

        // إضافة indexes لجدول clients للبحث السريع
        Schema::table('clients', function (Blueprint $table) {
            // Index للبحث بالاسم التجاري
            if (! SchemaGuard::hasIndex('clients', 'idx_clients_trade_name')) {
                $table->index('trade_name', 'idx_clients_trade_name');
            }
            // Index للهاتف
            if (! SchemaGuard::hasIndex('clients', 'idx_clients_phone')) {
                $table->index('phone', 'idx_clients_phone');
            }
            // Index للكود
            if (! SchemaGuard::hasIndex('clients', 'idx_clients_code')) {
                $table->index('code', 'idx_clients_code');
            }
        });

        // إضافة indexes لجدول invoices
        Schema::table('invoices', function (Blueprint $table) {
            // Index للنوع
            if (! SchemaGuard::hasIndex('invoices', 'idx_invoices_type')) {
                $table->index('type', 'idx_invoices_type');
            }
            
            // Index للتاريخ
            if (! SchemaGuard::hasIndex('invoices', 'idx_invoices_created_at')) {
                $table->index('created_at', 'idx_invoices_created_at');
            }
            
            // Index مركب للبحث
            if (! SchemaGuard::hasIndex('invoices', 'idx_invoices_type_created_by')) {
                $table->index(['type', 'created_by'], 'idx_invoices_type_created_by');
            }
        });

        // إضافة indexes لجدول pos_sessions
        Schema::table('pos_sessions', function (Blueprint $table) {
            // Index للجلسات النشطة
            if (! SchemaGuard::hasIndex('pos_sessions', 'idx_pos_sessions_active')) {
                $table->index(['status', 'user_id'], 'idx_pos_sessions_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_pos_search');
            $table->dropIndex('idx_products_name');
            $table->dropIndex('idx_products_barcode');
            $table->dropIndex('idx_products_sale_price');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_name');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_trade_name');
            $table->dropIndex('idx_clients_phone');
            $table->dropIndex('idx_clients_code');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_type');
            $table->dropIndex('idx_invoices_created_at');
            $table->dropIndex('idx_invoices_type_created_by');
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_pos_sessions_active');
        });
    }
};
