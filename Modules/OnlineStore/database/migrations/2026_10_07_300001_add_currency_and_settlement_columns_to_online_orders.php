<?php

use App\Support\CurrencySettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * طلبات المتجر: عملةُ الطلب، وهل أسعارُه شاملةٌ للضريبة، وحسابُ التحصيل.
 *
 *   • `currency` — عملةُ الطلب وقت إنشائه. كان الطلب بلا عملة فتُعرض مبالغُه
 *     بعملة النظام **الحالية**: تغييرُ العملة لاحقاً يغيّر رمزَ كل طلبٍ قديم.
 *   • `prices_include_tax` — الأسعارُ شاملةٌ للضريبة وقت البيع (الضريبة
 *     مستخرَجةٌ من السعر لا مضافةٌ عليه)؛ يلزم لعكس القيد بنفس التقسيم.
 *   • `payment_account_id` — الخزينة/الحساب الذي قُبض فيه مبلغُ الطلب فعلاً
 *     (فارغٌ ما دام الطلب ذمّةً لم تُحصَّل — الدفع عند الاستلام/التحويل).
 *   • `stock_returned_at` — متى أُعيد مخزونُ الطلب (حارسٌ من الإرجاع المكرَّر).
 *
 * idempotent: كل عمودٍ يُضاف إن لم يوجد، والتعبئة لا تمسّ إلا الفارغ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('online_orders')) {
            return;
        }

        Schema::table('online_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('online_orders', 'currency')) {
                $table->string('currency', 10)->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('online_orders', 'prices_include_tax')) {
                $table->boolean('prices_include_tax')->default(false)->after('tax_amount');
            }
            if (! Schema::hasColumn('online_orders', 'payment_account_id')) {
                $table->unsignedBigInteger('payment_account_id')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('online_orders', 'stock_returned_at')) {
                $table->timestamp('stock_returned_at')->nullable()->after('refund_amount');
            }
        });

        // الطلبات السابقة: عملتُها عملةُ فاتورتها إن وُجدت، وإلا عملة النظام الحالية
        // (`UPDATE … JOIN` صيغة MySQL — قاعدة SQLite للاختبار تبدأ فارغة)
        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('invoices', 'currency')) {
            DB::statement(
                'UPDATE online_orders o JOIN invoices i ON i.id = o.invoice_id
                 SET o.currency = i.currency
                 WHERE (o.currency IS NULL OR o.currency = "") AND i.currency IS NOT NULL AND i.currency <> ""'
            );
        }

        try {
            $code = CurrencySettings::code();
        } catch (Throwable $e) {
            $code = 'SAR';
        }

        DB::table('online_orders')
            ->where(function ($q) {
                $q->whereNull('currency')->orWhere('currency', '');
            })
            ->update(['currency' => $code]);
    }

    public function down(): void
    {
        // لا تراجع: الأعمدة تحمل بياناتٍ مالية للطلبات
    }
};
