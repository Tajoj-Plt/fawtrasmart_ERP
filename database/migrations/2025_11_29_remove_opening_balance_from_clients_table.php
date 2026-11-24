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
        Schema::table('clients', function (Blueprint $table) {
            // حذف حقول الرصيد الافتتاحي من جدول العملاء
            if (Schema::hasColumn('clients', 'opening_balance')) {
                $table->dropColumn('opening_balance');
            }
            if (Schema::hasColumn('clients', 'opening_balance_date')) {
                $table->dropColumn('opening_balance_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // إعادة إنشاء الحقول في حالة الـ rollback
            $table->decimal('opening_balance', 10)->nullable();
            $table->date('opening_balance_date')->nullable();
        });
    }
};
