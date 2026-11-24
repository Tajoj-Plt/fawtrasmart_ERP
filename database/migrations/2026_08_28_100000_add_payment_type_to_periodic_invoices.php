<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوع سداد الاشتراك الدوري: نقدي أم آجل.
 *
 * كان `PeriodicInvoiceGenerator` يُصدر كلَّ فاتورةٍ **مسودّةً غير مدفوعة**
 * (`payment_status = 3`) مهما كان الاشتراك، فلا يفترق اشتراكٌ يُحصَّل نقداً
 * عند الإصدار عن اشتراكٍ يُباع بالأجل. ولم يكن في النموذج ما يقول أيَّهما
 * هو: طريقةُ الدفع والخزينة حقلان يُحفَظان ولا يُقرآن، ومهلةُ السداد
 * (`payment_terms`) رقمُ أيامٍ لا إعلانَ نيّة.
 *
 * الافتراضي `credit`: هو ما كان يفعله المولّد فعلياً — فاتورةٌ غير مدفوعة
 * مستحقُّها كامل — فلا تنقلب اشتراكاتٌ قائمة إلى مدفوعةٍ بمجرّد الترقية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodic_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('periodic_invoices', 'payment_type')) {
                $table->enum('payment_type', ['cash', 'credit'])
                    ->default('credit')
                    ->after('treasury_id')
                    ->comment('نقدي: تصدر الفاتورة مدفوعة | آجل: تصدر بمستحقّ كامل');
            }
        });
    }

    public function down(): void
    {
        Schema::table('periodic_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('periodic_invoices', 'payment_type')) {
                $table->dropColumn('payment_type');
            }
        });
    }
};
