<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مِن أيّ مستودعٍ يبيع الموظّف: مستودعُه أم المستودع الرئيسي؟
 *
 * كان الجواب مثبَّتاً في الشيفرة: شاشاتُ البيع الثلاث (الفاتورة،
 * والمرتجع، والإشعار الدائن) تقرأ `default_warehouses` للموظّف، فإن لم
 * يكن له مستودعٌ مربوطٌ نزلت إلى المستودع الرئيسي. فمن أراد أن يبيع
 * موظّفوه كلُّهم من مستودعٍ واحدٍ مركزيّ — وله مستودعات مربوطة بهم
 * لأغراضٍ أخرى (الجرد، النقل، عهدة المندوب) — لم يجد أين يقول ذلك.
 *
 * العمود يقول أيَّ المصدرين هو الافتراضي:
 *
 *   • `employee` — مستودعات الموظّف المربوطة، والرئيسيُّ عند غيابها.
 *     وهو السلوك الذي كان، فهو الافتراضي هنا كي لا يتبدّل نظامٌ قائم
 *     بمجرّد الترحيل.
 *
 *   • `main` — المستودع الرئيسي (`store_houses.major = 1`) وحده،
 *     وتُتجاوَز مستودعات الموظّف المربوطة.
 *
 * وصلاحية «إنشاء فاتورة» المضبوطة على المستودع تبقى فوق هذا كلِّه:
 * الإعداد يختار المصدر، و`StorehouseAccess` يقول أيُّه يُعرَض ويُقبَل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_invoice_settings', 'warehouse_source')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->string('warehouse_source', 12)
                    ->default('employee')
                    ->comment('employee | main');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_invoice_settings', 'warehouse_source')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->dropColumn('warehouse_source');
            });
        }
    }
};
