<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نموذج «طلبات الشراء» (PurchaseOrdersRequestsController) يجمع «تاريخ
 * الاستحقاق» فعلاً (`config['due_date'] => true` في partials/form.blade.php)
 * ويرسله `due_date` مع كل حفظ — لكن لا عمود بهذا الاسم في `purchase_invoices`،
 * فالمتحكّم لا يقرأه إطلاقاً ويضيع صامتاً. تبويب التفاصيل في شاشة العرض
 * يعرض «غير محدَّد» دائماً مهما أُدخل.
 *
 * الجدول مشترك بين أربعة أنواع مستندات (`type`: invoice/Return/Requested/
 * City Notice) — العمود آمنٌ للجميع (nullable)، ويُفعَّل الكتابة فيه هنا
 * لقسم «طلبات الشراء» وحده، فلا يغيّر سلوك الأقسام الثلاثة الأخرى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
