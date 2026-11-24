<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جدولة الفواتير الدورية ومنع السداد الجزئي.
 *
 * ══════════ `periodic_invoice_instances` جدولٌ لا سجلُّ إصدار ══════════
 *
 * كان الصفُّ لا يُكتب إلّا بعد أن تصدر الفاتورة، فعمود `invoice_id` إلزاميّ،
 * والاشتراك يُحفظ ولا جدولَ له: لا يُرى متى تصدر نسخه، ولا يُعرف أنّ نسخةً
 * أخفقت ولماذا. صار الجدولُ يُكتب **عند الحفظ** بصفوفٍ «مجدولة» بلا فاتورة،
 * والمهمّةُ المجدولة تُكمل كلَّ صفٍّ حان إصدارُه:
 *
 *     1 مجدولة  ← 2 صدرت (ومعها `invoice_id`)
 *               ← 4 أخفقت (ومعها `last_error` — نقصُ مخزون، حدٌّ ائتماني…)
 *     3 ملغاة   — نسخةٌ خرجت من الجدول بتعديل الاشتراك
 *
 * و`issue_date` يوم الإصدار (الاستحقاق − مهلة الإصدار)، و`due_date` يوم
 * الاستحقاق: منه تُحسب «شروط الدفع» على الفاتورة الصادرة، فيقرؤه تنبيه
 * `invoices:payment-reminders` وتقارير الأعمار.
 *
 * ══════════ `periodic_invoices.end_date` ══════════
 *
 * الاشتراك كان ينتهي بعدد الفواتير وحده. يُضاف تاريخُ نهاية: ما استحقّ
 * بعده لا يُجدوَل، وأيُّهما وقع أوّلاً أنهى الاشتراك.
 *
 * ══════════ `invoices.disable_partial_payment` ══════════
 *
 * خيار «منع السداد الجزئي» يُحفظ على الاشتراك ولا يقرؤه شيء. يُنسخ الآن إلى
 * كلّ فاتورةٍ يُصدرها، وتقرؤه شاشة الدفعات وسند القبض. على الفاتورة لا على
 * الاشتراك: الفاتورة تبقى ممنوعةً من التجزئة ولو عُدِّل الاشتراك أو حُذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodic_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('periodic_invoices', 'end_date')) {
                $table->date('end_date')->nullable()->after('repeat_count')
                    ->comment('آخر يوم استحقاق يُجدوَل — مع عدد الفواتير أيّهما أسبق');
            }

            if (! Schema::hasColumn('periodic_invoices', 'shipping_tax_id')) {
                $table->unsignedBigInteger('shipping_tax_id')->nullable()->after('shipping_tax')
                    ->comment('ضريبة الشحن المختارة — تُمرَّر إلى كل فاتورة تصدر');
            }
        });

        // عدد الفواتير اختياريٌّ متى ضُبط تاريخ نهاية
        Schema::table('periodic_invoices', function (Blueprint $table) {
            $table->integer('repeat_count')->nullable()->change();
        });

        Schema::table('periodic_invoice_instances', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();

            if (! Schema::hasColumn('periodic_invoice_instances', 'issue_date')) {
                $table->date('issue_date')->nullable()->after('due_date')
                    ->comment('يوم الإصدار = الاستحقاق − مهلة الإصدار');
            }

            if (! Schema::hasColumn('periodic_invoice_instances', 'last_error')) {
                $table->text('last_error')->nullable()->after('status');
            }

            if (! Schema::hasColumn('periodic_invoice_instances', 'attempted_at')) {
                $table->timestamp('attempted_at')->nullable()->after('last_error');
            }
        });

        /* الصفوف القائمة كتبها المولّد السابق بعد الإصدار، بحالة ١ أو ٢
           تعني «مدفوعة/عليها مستحقّ». كلُّها صدرت، فتصير ٢. */
        DB::table('periodic_invoice_instances')->whereNotNull('invoice_id')->update(['status' => 2]);

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'disable_partial_payment')) {
                $table->boolean('disable_partial_payment')->default(false)
                    ->comment('تُسدَّد كاملةً دفعةً واحدة — من الاشتراك الدوري');
            }
        });

        // الفواتير التي صدرت من اشتراكٍ مانعٍ للتجزئة قبل الترقية
        DB::table('invoices')
            ->whereIn('id', DB::table('periodic_invoice_instances as i')
                ->join('periodic_invoices as p', 'p.id', '=', 'i.periodic_invoice_id')
                ->where('p.disable_partial_payment', true)
                ->whereNotNull('i.invoice_id')
                ->select('i.invoice_id'))
            ->update(['disable_partial_payment' => true]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'disable_partial_payment')) {
                $table->dropColumn('disable_partial_payment');
            }
        });

        Schema::table('periodic_invoice_instances', function (Blueprint $table) {
            foreach (['issue_date', 'last_error', 'attempted_at'] as $column) {
                if (Schema::hasColumn('periodic_invoice_instances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('periodic_invoices', function (Blueprint $table) {
            foreach (['end_date', 'shipping_tax_id'] as $column) {
                if (Schema::hasColumn('periodic_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
