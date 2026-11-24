<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قيمة الحدّ الأقصى للخصم — ومعها إسقاط «تفعيل تسوية المبيعات».
 *
 * ── الحدّ الأقصى للخصم ──
 *
 * كان `enable_max_discount` مفتاحاً بلا رقم: يُفعَّل في الشاشة ثمّ يقرأ
 * `InvoicesController` النسبةَ من إعداد فرعٍ اسمه `max_discount_percent`
 * في «إعدادات الفروع» — شاشةٍ أخرى في وحدةٍ أخرى لا تذكرها شاشة الفواتير
 * ولا تحيل إليها. فمن فعّل المفتاح لم يجد أين يكتب النسبة، ومن لم يبحث
 * عنها في الفروع سرى عليه الافتراضي (١٠٪) بلا أن يختاره.
 *
 * العمود هنا هو **قيمة المنشأة**، وإعداد الفرع يبقى **تجاوزاً** لها متى
 * ضبطه الفرع بنفسه (`branch_setting_source() === 'own'`). فمن له فرعٌ
 * واحد يضبط الرقم من هذه الشاشة ولا يعرف بالفروع شيئاً، ومن يفرّق بين
 * فروعه يبقى تفريقُه عاملاً.
 *
 * ── تسوية المبيعات ──
 *
 * `enable_sales_adjustment` يُسقَط بطلب المستخدم: حقلُ «تسوية» يُزاد على
 * الفاتورة أو يُحسم منها ببيانٍ حرّ، وهو بابٌ يلتفّ على الخصم والضريبة
 * معاً. أعمدة `invoices.adjustment_*` تبقى كما هي — الفواتير القديمة
 * تُطبَع بتسوياتها، والإسقاط يمنع الجديد لا يمحو القديم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_invoice_settings', 'max_discount_percent')) {
                // ١٠٪ هي القيمة التي كان `InvoicesController` يمرّرها
                // افتراضاً لـ`branch_setting()`، فالسلوك لا يتغيّر بالترحيل
                $table->decimal('max_discount_percent', 5, 2)
                    ->default(10)
                    ->after('last_price_display');
            }
        });

        if (Schema::hasColumn('sales_invoice_settings', 'enable_sales_adjustment')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                if (Schema::hasColumn('sales_invoice_settings', 'enable_sales_adjustment')) {
                    $table->dropColumn('enable_sales_adjustment');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_invoice_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sales_invoice_settings', 'max_discount_percent')) {
                $table->dropColumn('max_discount_percent');
            }

            if (! Schema::hasColumn('sales_invoice_settings', 'enable_sales_adjustment')) {
                $table->boolean('enable_sales_adjustment')->default(false);
            }
        });
    }
};
