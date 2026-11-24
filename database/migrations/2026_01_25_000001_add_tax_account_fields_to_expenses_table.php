<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقول حساب الضريبة لجدول المصروفات
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // حساب الضريبة الأولى (VAT_PAYABLE = 37 أو VAT_PAID = 2996)
            if (! Schema::hasColumn('expenses', 'tax1_account_id')) {
                $table->unsignedBigInteger('tax1_account_id')->nullable()->after('tax1_amount');
            }
            
            // حساب الضريبة الثانية (VAT_PAYABLE = 37 أو VAT_PAID = 2996)
            if (! Schema::hasColumn('expenses', 'tax2_account_id')) {
                $table->unsignedBigInteger('tax2_account_id')->nullable()->after('tax2_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['tax1_account_id', 'tax2_account_id']);
        });
    }
};
