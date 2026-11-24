<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الإشعارات المدينة في المبيعات.
 *
 * «تفعيل الإشعار المدين» كان مفتاحاً في «إعدادات الفواتير» بلا مستندٍ
 * يفتحه: لا جدول، ولا نموذج، ولا شاشة. وشرحُ المفتاح يقول ذلك: «شاشةٌ لم
 * تُبنَ بعد». والموجود في النظام هو نظيرُه المقابل — «الإشعار الدائن»
 * (`credit_notifications`) الذي **ينقص** ذمّة العميل.
 *
 * ── ما الإشعار المدين ──
 *
 * مستندٌ **يزيد** ذمّة العميل بعد صدور الفاتورة: فرقُ سعرٍ اكتُشف متأخّراً،
 * رسمُ خدمةٍ أو تركيبٍ لم يُدرَج، غرامةُ تأخير، مصروفُ شحنٍ تحمّله البائع
 * ثمّ حُمِّل على المشتري. وهو **لا يمسّ المخزون**: لا بضاعةَ تُصرَف ولا
 * تُردّ — ولذلك جدولُه أبسط كثيراً من جدول الإشعار الدائن، بلا مستودعٍ
 * ولا إذنِ صرف.
 *
 * ── القيد ──
 *
 *   مدين  حساب العميل            بالإجمالي شاملاً الضريبة
 *   دائن  الإيراد (أو المبيعات)  بالمبلغ قبل الضريبة
 *   دائن  ضريبة القيمة المضافة   بالضريبة إن وُجدت
 *
 * أي عكسُ قيد الإشعار الدائن تماماً. والحسابات من «الحسابات المحاسبية»
 * وحدها — انظر `ResolvesAccountingAccounts`.
 *
 * ── الحالة ──
 *
 * مستندٌ محاسبيٌّ صدر لا يُعدَّل ولا يُحذف، كالفاتورة: يُلغى بقيدٍ عكسيّ
 * (`cancelled`) فيبقى أثرُه في الدفتر ويظهر الإلغاء بجانبه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_debit_notes')) {
            Schema::create('sales_debit_notes', function (Blueprint $table) {
                $table->id();

                // رقم المستند من «الترقيم المتسلسل» — قسم `sales-debit`
                $table->string('code', 50)->unique();

                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

                /* الفاتورة التي صدر عنها الإشعار — اختيارية: قد يصدر إشعارٌ
                   مدينٌ عن اتفاقٍ عامّ لا عن فاتورةٍ بعينها. `nullOnDelete`
                   لا `cascade`: حذفُ فاتورةٍ لا يمحو مستنداً محاسبياً صدر. */
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->date('note_date');

                // سببُ الإصدار — يُطبَع على المستند ويظهر في القائمة
                $table->string('reason', 255)->nullable();

                // العملة تُجمَّد مع المستند كما في بقيّة مستندات المبيعات
                $table->string('currency', 10)->nullable();

                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('total_tax', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);

                // draft لا وجود لها هنا: المستند يُرحَّل عند حفظه أو لا يُحفظ
                $table->enum('status', ['posted', 'cancelled'])->default('posted');

                $table->text('notes')->nullable();

                // القيد المُرحَّل — منه يُبنى القيد العكسي عند الإلغاء
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();

                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['client_id', 'note_date']);
                $table->index(['status', 'note_date']);
            });
        }

        if (! Schema::hasTable('sales_debit_note_items')) {
            Schema::create('sales_debit_note_items', function (Blueprint $table) {
                $table->id();

                $table->foreignId('sales_debit_note_id')
                    ->constrained('sales_debit_notes')
                    ->cascadeOnDelete();

                /* البند وصفٌ حرّ لا منتج: الإشعار المدين رسمٌ أو فرقُ سعرٍ لا
                   بضاعةٌ تُسلَّم. و`product_id` يبقى اختيارياً لمن أراد ربطه
                   بصنفٍ بعينه (فرقُ سعرٍ على منتج) — بلا أثرٍ في المخزون. */
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

                $table->string('description', 500);
                $table->decimal('quantity', 15, 4)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);

                // الضريبة المضبوطة في «إعدادات الضريبة» — نسبتُها **ونوعها**
                $table->unsignedBigInteger('tax_id')->nullable()->index();
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);

                // صافي البند قبل الضريبة، والإجمالي بعدها
                $table->decimal('net_total', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_debit_note_items');
        Schema::dropIfExists('sales_debit_notes');
    }
};
