<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة الفاتورة الدورية الناقصة.
 *
 * `PeriodicInvoicesController::store()` يمرّر إلى `create()` ستّة حقول
 * لا وجود لها في الجدول ولا في `$fillable`:
 *
 *     'before_days'      → العمود اسمه `invoice_days_offset`
 *     'show_dates'       → العمود اسمه `show_from_to_dates`
 *     'disable_partial'  → العمود اسمه `disable_partial_payment`
 *     'send_copy'        → لا عمود له إطلاقاً
 *     'shipping_tax'     → تُحسَب ثمّ تُرمى
 *     'tax_rate'         → تُحسَب ثمّ تُرمى
 *
 * وثلاثتها الأولى لها أعمدةٌ بأسماءٍ أخرى **لا يكتب إليها شيء** — فالحفظ
 * يمرّ بلا خطأ، ويخسر المستخدم بصمت: مهلةَ إصدار الفاتورة قبل موعدها،
 * وإظهارَ نطاق التاريخ، ومنعَ السداد الجزئي. والتحقّق في `rules()` يقبل
 * الأسماء الخاطئة فيبدو كلّ شيء سليماً.
 *
 * ويُضاف هنا ما تحتاجه الفاتورة الدورية لتعمل بإعدادات النظام كبقيّة
 * مستندات المبيعات: العملة تُجمَّد مع المستند، ومسؤول المبيعات،
 * والمستودع، وطريقة الدفع وخزينتها، والمرفقات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodic_invoices', function (Blueprint $table) {
            // العملة تُجمَّد مع المستند
            if (! Schema::hasColumn('periodic_invoices', 'currency')) {
                $table->string('currency', 10)->nullable()->after('invoice_number');
            }

            if (! Schema::hasColumn('periodic_invoices', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('created_by')->index();
            }

            if (! Schema::hasColumn('periodic_invoices', 'store_house_id')) {
                $table->unsignedBigInteger('store_house_id')->nullable()->after('employee_id')->index();
            }

            if (! Schema::hasColumn('periodic_invoices', 'payment_method')) {
                $table->unsignedBigInteger('payment_method')->nullable()->after('grand_total');
            }

            if (! Schema::hasColumn('periodic_invoices', 'treasury_id')) {
                $table->unsignedBigInteger('treasury_id')->nullable()->after('payment_method');
            }

            // ضريبة الشحن ونسبة الضريبة — تُحسبان في الحفظ ثمّ تُرميان
            if (! Schema::hasColumn('periodic_invoices', 'shipping_tax')) {
                $table->decimal('shipping_tax', 10, 2)->nullable()->default(0)->after('shipping_cost');
            }

            if (! Schema::hasColumn('periodic_invoices', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 2)->nullable()->default(0)->after('tax_type');
            }

            // إرسال نسخة للعميل عند إصدار كل فاتورة — يُرسله النموذج ولا
            // عمود يستقبله
            if (! Schema::hasColumn('periodic_invoices', 'send_copy')) {
                $table->boolean('send_copy')->default(false)->after('auto_generate');
            }

            if (! Schema::hasColumn('periodic_invoices', 'attachments')) {
                $table->string('attachments')->nullable()->after('notes');
            }
        });

        // العملة للفواتير الدورية القائمة: تُملأ بعملة النظام لا تُترك
        // فارغة، وإلا طُبعت بلا رمز وقُرئت في التقارير كأنّها بلا عملة
        if (Schema::hasColumn('periodic_invoices', 'currency')) {
            try {
                \Illuminate\Support\Facades\DB::table('periodic_invoices')
                    ->whereNull('currency')
                    ->update(['currency' => \App\Support\CurrencySettings::code()]);
            } catch (\Throwable $e) {
                // إعدادات العملة غير مهيّأة بعد — تُقرأ بالافتراضية وقت العرض
            }
        }
    }

    public function down(): void
    {
        Schema::table('periodic_invoices', function (Blueprint $table) {
            foreach ([
                'currency', 'employee_id', 'store_house_id', 'payment_method',
                'treasury_id', 'shipping_tax', 'tax_rate', 'send_copy', 'attachments',
            ] as $column) {
                if (Schema::hasColumn('periodic_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
