<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة حقول نظام الخصومات والقسائم المحسّن
     * 
     * rewards: حقول ضبط الخصم والقسائم (الحد الأقصى للخصم، الحد الأدنى للطلب، صلاحية القسيمة)
     * reward_redemptions: حقول تتبع استخدام القسائم والخصومات (المبلغ المطبق عليه، الخصم المحسوب، الفاتورة، بيانات القسيمة)
     */
    public function up(): void
    {
        // ====================================
        // حقول جديدة في جدول المكافآت (rewards)
        // ====================================
        Schema::table('rewards', function (Blueprint $table) {
            // الحد الأقصى لقيمة الخصم (مهم لنوع discount_percentage لتحديد سقف الخصم)
            if (! Schema::hasColumn('rewards', 'max_discount_value')) {
                $table->decimal('max_discount_value', 15, 2)->nullable()->after('reward_value')
                    ->comment('الحد الأقصى لقيمة الخصم (للنسبة المئوية)');
            }
            
            // الحد الأدنى لقيمة الطلب/الفاتورة لتطبيق الخصم
            if (! Schema::hasColumn('rewards', 'min_order_amount')) {
                $table->decimal('min_order_amount', 15, 2)->nullable()->after('max_discount_value')
                    ->comment('الحد الأدنى لمبلغ الفاتورة لتطبيق الخصم');
            }
            
            // عدد أيام صلاحية القسيمة/الكود بعد الإصدار
            if (! Schema::hasColumn('rewards', 'validity_days')) {
                $table->unsignedInteger('validity_days')->nullable()->after('min_order_amount')
                    ->comment('عدد أيام صلاحية القسيمة بعد الإصدار');
            }
        });

        // ====================================
        // حقول جديدة في جدول طلبات الاستبدال (reward_redemptions)
        // ====================================
        Schema::table('reward_redemptions', function (Blueprint $table) {
            // المبلغ الذي تم تطبيق الخصم عليه (مبلغ الفاتورة)
            if (! Schema::hasColumn('reward_redemptions', 'applied_to_amount')) {
                $table->decimal('applied_to_amount', 15, 2)->nullable()->after('reward_value')
                    ->comment('مبلغ الفاتورة الذي تم تطبيق الخصم عليه');
            }
            
            // قيمة الخصم المحسوبة فعلياً (بعد تطبيق النسبة والحد الأقصى)
            if (! Schema::hasColumn('reward_redemptions', 'calculated_discount')) {
                $table->decimal('calculated_discount', 15, 2)->nullable()->after('applied_to_amount')
                    ->comment('قيمة الخصم الفعلية المحسوبة');
            }
            
            // رقم الفاتورة التي تم استخدام القسيمة/الخصم فيها
            if (! Schema::hasColumn('reward_redemptions', 'used_in_invoice_id')) {
                $table->unsignedBigInteger('used_in_invoice_id')->nullable()->after('calculated_discount')
                    ->comment('الفاتورة التي استُخدمت فيها القسيمة');
            }
            
            // الرقم التسلسلي الفريد للقسيمة
            if (! Schema::hasColumn('reward_redemptions', 'voucher_serial')) {
                $table->string('voucher_serial', 50)->nullable()->unique()->after('voucher_code')
                    ->comment('الرقم التسلسلي للقسيمة');
            }
            
            // رمز التحقق PIN للقسيمة
            if (! Schema::hasColumn('reward_redemptions', 'voucher_pin')) {
                $table->string('voucher_pin', 10)->nullable()->after('voucher_serial')
                    ->comment('رمز التحقق PIN');
            }
            
            // تاريخ انتهاء صلاحية القسيمة
            if (! Schema::hasColumn('reward_redemptions', 'voucher_expires_at')) {
                $table->date('voucher_expires_at')->nullable()->after('voucher_pin')
                    ->comment('تاريخ انتهاء صلاحية القسيمة');
            }
            
            // حالة القسيمة
            if (! Schema::hasColumn('reward_redemptions', 'voucher_status')) {
                $table->enum('voucher_status', ['active', 'used', 'expired', 'cancelled'])
                    ->default('active')->after('voucher_expires_at')
                    ->comment('حالة القسيمة: نشطة، مستخدمة، منتهية، ملغاة');
            }
        });
    }

    /**
     * التراجع عن التغييرات
     */
    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn(['max_discount_value', 'min_order_amount', 'validity_days']);
        });

        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropColumn([
                'applied_to_amount',
                'calculated_discount', 
                'used_in_invoice_id',
                'voucher_serial',
                'voucher_pin',
                'voucher_expires_at',
                'voucher_status',
            ]);
        });
    }
};
