<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حسابا الضريبة والإجمالي في سندات القبض.
 *
 * كان الجدول يحفظ `tax1` و`tax2` ومبلغيهما — ثم لا شيء. القيد المحاسبي
 * لسند القبض يُنشأ بسطرين فقط (الخزينة مدينة بالمبلغ، والحساب المقابل
 * دائن به) فلا يُرحَّل مبلغ الضريبة إلى أي حساب، ولا يُعرف أيّ حساب
 * كان ينبغي أن يستقبله.
 *
 * فالضريبة كانت تُحسب في الشاشة وتُحفظ في السجلّ ولا تظهر في الدفاتر
 * إطلاقاً — إقرارٌ ضريبي ناقص.
 *
 * الأعمدة هنا تطابق ما في `expenses` (هجرة 2026_01_25_000001)، وتُملأ
 * من «الحسابات المحاسبية» في الإعدادات: `vat_payable` و`vat_paid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('receipts', 'tax1_account_id')) {
                $table->unsignedBigInteger('tax1_account_id')->nullable()->after('tax1_amount');
            }

            if (! Schema::hasColumn('receipts', 'tax2_account_id')) {
                $table->unsignedBigInteger('tax2_account_id')->nullable()->after('tax2_amount');
            }

            if (! Schema::hasColumn('receipts', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            foreach (['tax1_account_id', 'tax2_account_id', 'total_amount'] as $column) {
                if (Schema::hasColumn('receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
