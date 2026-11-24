<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عملة فاتورة الشراء — تُحفظ مع المستند عند إنشائه وتُعرض منه.
 *
 * `purchase_invoices` لم يكن يحمل عمود عملة: الفاتورة تُعرض دائماً بالعملة
 * الافتراضية **الحالية**، فإن غُيّرت العملة الافتراضية لاحقاً ظهرت الفواتير
 * القديمة برمزٍ غير الذي صدرت به. القيد المحاسبي (`journal_entries.currency`)
 * والإذن المخزني (`warehouse_permits.currency`) يحملانها أصلاً.
 *
 * العمود يقبل NULL: الفواتير الأقدم منه تبقى بلا عملة محفوظة وتُعرض
 * بالافتراضية كما كانت — لا تُملأ هنا بأثرٍ رجعي بتخمين.
 *
 * الترحيل متكرّر الأمان (idempotent): لا يفعل شيئاً إن كان العمود موجوداً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_invoices') || Schema::hasColumn('purchase_invoices', 'currency')) {
            return;
        }

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('grand_total');
        });
    }

    public function down(): void
    {
        // لا يُحذف العمود: قد تكون فواتير حُفظت بعملتها بعد الترحيل
    }
};
