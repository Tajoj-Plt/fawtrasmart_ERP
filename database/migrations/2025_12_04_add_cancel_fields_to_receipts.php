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
        Schema::table('receipts', function (Blueprint $table) {
            // إضافة حقل الحالة
            if (!Schema::hasColumn('receipts', 'status')) {
                $table->tinyInteger('status')->default(1)->comment('1: نشط، 0: ملغي');
            }

            // إضافة حقل الملاحظات
            if (!Schema::hasColumn('receipts', 'notes')) {
                $table->text('notes')->nullable();
            }

            // إضافة تاريخ الإلغاء
            if (!Schema::hasColumn('receipts', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }

            // إضافة معرف المستخدم الذي ألغاه
            if (!Schema::hasColumn('receipts', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            if (Schema::hasColumn('receipts', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('receipts', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('receipts', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('receipts', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
        });
    }
};
