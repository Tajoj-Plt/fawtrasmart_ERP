<?php

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
        Schema::table('sales_targets', function (Blueprint $table) {
            // دورة العمولة: شهري أو أسبوعي
            if (! Schema::hasColumn('sales_targets', 'commission_period')) {
                $table->enum('commission_period', ['monthly', 'weekly'])->default('monthly')->after('status');
            }
            // هل الحساب تلقائي للأسبوعي
            if (! Schema::hasColumn('sales_targets', 'auto_calculate_weekly')) {
                $table->boolean('auto_calculate_weekly')->default(false)->after('commission_period');
            }
        });

        // إضافة حقول للتسويات لتتبع الصرف المباشر
        Schema::table('sales_target_settlements', function (Blueprint $table) {
            // هل تم الصرف المباشر (للأسبوعية)
            if (! Schema::hasColumn('sales_target_settlements', 'paid_directly')) {
                $table->boolean('paid_directly')->default(false)->after('is_settled');
            }
            // رقم القيد المحاسبي للصرف المباشر
            if (! Schema::hasColumn('sales_target_settlements', 'direct_journal_entry_id')) {
                $table->unsignedBigInteger('direct_journal_entry_id')->nullable()->after('paid_directly');
            }
            // تاريخ الصرف المباشر
            if (! Schema::hasColumn('sales_target_settlements', 'direct_paid_at')) {
                $table->datetime('direct_paid_at')->nullable()->after('direct_journal_entry_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->dropColumn(['commission_period', 'auto_calculate_weekly']);
        });

        Schema::table('sales_target_settlements', function (Blueprint $table) {
            $table->dropColumn(['paid_directly', 'direct_journal_entry_id', 'direct_paid_at']);
        });
    }
};
