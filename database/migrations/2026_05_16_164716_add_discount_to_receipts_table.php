<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // الخصم المسموح به الذي يمنحه المدير للعميل عند تحرير سند القبض.
            // amount = المبلغ المستحق على العميل، paid = amount - discount_amount = ما دخل الخزينة.
            if (! Schema::hasColumn('receipts', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
