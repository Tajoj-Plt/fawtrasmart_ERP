<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * حذف حقل accounting_system_per_invoice واستبداله بـ default_bank_account_id
     */
    public function up(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            // حذف حقل نظام المحاسبة لكل فاتورة
            if (Schema::hasColumn('pos_general_settings', 'accounting_system_per_invoice')) {
                $table->dropColumn('accounting_system_per_invoice');
            }
            
            // إضافة حقل البنك الافتراضي (مرتبط بجدول الحسابات)
            if (! Schema::hasColumn('pos_general_settings', 'default_bank_account_id')) {
                $table->foreignId('default_bank_account_id')
                    ->nullable()
                    ->after('loss_account_id')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            // إعادة حقل نظام المحاسبة لكل فاتورة
            $table->boolean('accounting_system_per_invoice')->default(false)->after('show_print_window_after_confirm');
            
            // حذف حقل البنك الافتراضي
            $table->dropForeign(['default_bank_account_id']);
            $table->dropColumn('default_bank_account_id');
        });
    }
};
