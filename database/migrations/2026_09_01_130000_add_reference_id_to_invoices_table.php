<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فاتورة المرتجع (`type = 'returned'`) لا تحتفظ برقم الفاتورة الأصلية
     * التي أُرجعت عنها — `reference_number` نصٌّ حرّ لا يُضمن تطابقه مع
     * `code` الأصلية. فلا طريقة موثوقة لمعرفة «هل لهذه الفاتورة مرتجع؟»
     * لاستبعادها من شاشة السداد المجمَّع، رغم أن العميل يختار الفاتورة
     * الأصلية فعلياً عند إنشاء المرتجع (`ReturnInvoiceController::store`).
     *
     * نفس نمط `purchase_invoices.reference_id` بالضبط — بلا قيد FK صريح،
     * فهرسٌ فقط، تماشياً مع الجدول الآخر.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->index()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('reference_id');
        });
    }
};
