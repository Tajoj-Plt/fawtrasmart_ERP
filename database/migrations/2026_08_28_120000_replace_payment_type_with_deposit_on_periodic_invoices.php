<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الإيداع على الاشتراك الدوري — بدل مُنتقي «نوع السداد».
 *
 * كان الاشتراك يحمل `payment_type` بقيمتين (`cash`/`credit`) يقرؤهما
 * المولّد ليقرّر حالة كلّ فاتورةٍ يُصدرها: نقديٌّ مدفوعةً بالكامل، وآجلٌ
 * بمستحقٍّ كامل. ومنتقٍ بقيمتين لا يصف الواقع: من يقبض ثلث القيمة عند
 * الإصدار ليس نقدياً ولا آجلاً، فلا يجد في القائمة ما يقوله.
 *
 * فصار الاشتراك يحمل ما تحمله الفاتورة نفسُها في شاشة «إنشاء فاتورة
 * مبيعات» — `advance_payment` و`is_paid` — فتخرج نسخُه بحالةٍ ومستحقٍّ
 * محسوبين من مبلغٍ لا من تسمية:
 *
 *   • `is_paid`         مدفوعةٌ بالكامل: مستحقٌّ صفر، والمبلغ كلُّه مقبوض.
 *   • `advance_payment` مقبوضٌ عند الإصدار، والباقي مستحقّ.
 *   • كلاهما صفر        فاتورةٌ آجلة بمستحقٍّ كامل — سلوكُ `credit` نفسُه.
 *
 * ونقلُ البيانات يحفظ ما كان: `cash` كانت تعني «مدفوعةً بالكامل»، فتصير
 * `is_paid = 1`؛ و`credit` كانت الافتراضي فتبقى صفرين.
 *
 * @see \App\Services\Invoices\PeriodicInvoiceGenerator::issue()
 * @see 2026_08_28_100000_add_payment_type_to_periodic_invoices الهجرة المُستبدَلة
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodic_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('periodic_invoices', 'advance_payment')) {
                $table->decimal('advance_payment', 15, 2)
                    ->default(0)
                    ->after('treasury_id')
                    ->comment('المقبوض عند إصدار كل نسخة — الباقي مستحقّ على العميل');
            }

            if (! Schema::hasColumn('periodic_invoices', 'is_paid')) {
                $table->boolean('is_paid')
                    ->default(false)
                    ->after('advance_payment')
                    ->comment('تصدر كل نسخة مدفوعةً بالكامل بمستحقّ صفر');
            }
        });

        // ما كان `cash` كان يُصدر فاتورةً مدفوعةً بالكامل — فلا تنقلب
        // اشتراكاتٌ قائمة إلى آجلة بمجرّد الترقية
        if (Schema::hasColumn('periodic_invoices', 'payment_type')) {
            DB::table('periodic_invoices')->where('payment_type', 'cash')->update(['is_paid' => true]);

            Schema::table('periodic_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('periodic_invoices', 'payment_type')) {
                    $table->dropColumn('payment_type');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('periodic_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('periodic_invoices', 'payment_type')) {
                $table->enum('payment_type', ['cash', 'credit'])
                    ->default('credit')
                    ->after('treasury_id');
            }
        });

        DB::table('periodic_invoices')->where('is_paid', true)->update(['payment_type' => 'cash']);

        Schema::table('periodic_invoices', function (Blueprint $table) {
            $table->dropColumn(['advance_payment', 'is_paid']);
        });
    }
};
