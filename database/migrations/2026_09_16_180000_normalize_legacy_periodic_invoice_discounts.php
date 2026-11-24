<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * خصم الاشتراكات الدورية المحفوظة قبل توحيد الحساب.
 *
 * كان `PeriodicInvoicesController` يحفظ في `discount_amount` الخصمَ **مبلغاً
 * محسوباً** ولو اختار المستخدم «نسبة»، بجوار `discount_type = 2`. صارت الشاشة
 * تحفظ ما كُتب كما كُتب (قيمةً أو نسبة) — فشاشةُ التعديل تقرأ ذلك المبلغ
 * القديم نسبةً: خصمُ ٣٠ ريالاً يظهر ٣٠٪ ويُحفظ بها عند أوّل تعديل.
 *
 * التحويل يحفظ المال نفسه: النوع يصير «مبلغاً»، والقيمةُ خصمُ المستند كما حُسب
 * (`total_discount` ناقصَ خصوم البنود). ولا يمسّ اشتراكاً حُفظ بعد التوحيد إلّا
 * بما يساويه، إذ خصمُه بالمبلغ نفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('periodic_invoices') || ! Schema::hasColumn('periodic_invoices', 'total_discount')) {
            return;
        }

        DB::table('periodic_invoices')
            ->where('discount_type', 2)
            ->orderBy('id')
            ->each(function ($row) {
                $lineDiscounts = (float) DB::table('invoice_items')
                    ->where('periodic_invoice_id', $row->id)
                    ->sum('discount');

                DB::table('periodic_invoices')->where('id', $row->id)->update([
                    'discount_type' => 1,
                    'discount_amount' => max(0, round((float) $row->total_discount - $lineDiscounts, 2)),
                ]);
            });
    }

    public function down(): void
    {
        // المبلغ المحفوظ صحيحٌ في الحالتين — لا نسبةَ أصليةً تُستعاد
    }
};
