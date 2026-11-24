<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقول إيرادات الشحن وتكلفة شركة الشحن للطلبات الإلكترونية
 * Add shipping revenue and shipping company cost fields to online orders
 * 
 * هذه الحقول تُستخدم لـ:
 * - تتبع نسبة إيرادات الشحن المطبقة على كل طلب
 * - تخزين مبلغ إيرادات الشحن الفعلي
 * - تخزين تكلفة الشحن المستحقة لشركة الشحن
 * - معرفة هل تم صرف مبلغ الشحن لشركة الشحن أم لا
 * 
 * عند الإلغاء أو الاسترداد:
 * - إذا تم الشحن (is_shipping_paid = true): يتم عكس قيد صرف المبلغ لشركة الشحن
 * - يتم عكس إيرادات الشحن والتزام شركة الشحن
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            // نسبة إيرادات الشحن المطبقة (من الإعدادات وقت إنشاء الطلب)
            if (! Schema::hasColumn('online_orders', 'shipping_revenue_percentage')) {
                $table->decimal('shipping_revenue_percentage', 5, 2)->default(0)->after('shipping_cost')
                    ->comment('نسبة إيرادات الشحن المطبقة على الطلب');
            }
            
            // مبلغ إيرادات الشحن (الربح من الشحن)
            if (! Schema::hasColumn('online_orders', 'shipping_revenue_amount')) {
                $table->decimal('shipping_revenue_amount', 10, 2)->default(0)->after('shipping_revenue_percentage')
                    ->comment('مبلغ إيرادات الشحن (الربح)');
            }
            
            // تكلفة الشحن المستحقة لشركة الشحن
            if (! Schema::hasColumn('online_orders', 'shipping_company_cost')) {
                $table->decimal('shipping_company_cost', 10, 2)->default(0)->after('shipping_revenue_amount')
                    ->comment('تكلفة الشحن المستحقة لشركة الشحن');
            }
            
            // هل تم صرف مبلغ الشحن لشركة الشحن؟
            if (! Schema::hasColumn('online_orders', 'is_shipping_paid')) {
                $table->boolean('is_shipping_paid')->default(false)->after('shipping_company_cost')
                    ->comment('هل تم صرف مبلغ الشحن لشركة الشحن؟');
            }
            
            // تاريخ صرف مبلغ الشحن لشركة الشحن
            if (! Schema::hasColumn('online_orders', 'shipping_paid_at')) {
                $table->timestamp('shipping_paid_at')->nullable()->after('is_shipping_paid')
                    ->comment('تاريخ صرف مبلغ الشحن لشركة الشحن');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_revenue_percentage',
                'shipping_revenue_amount',
                'shipping_company_cost',
                'is_shipping_paid',
                'shipping_paid_at',
            ]);
        });
    }
};
