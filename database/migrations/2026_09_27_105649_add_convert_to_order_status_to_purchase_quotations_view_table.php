<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حالة «حُوِّل إلى أمر شراء» لعرض السعر — كان `convertToPurchaseOrder()` يكتب
 * `status = 'approval'` بعد التحويل، أي **نفس القيمة** التي يشترطها الشرط
 * قبله (`status != 'approval'` يمنع التحويل)، فلا يتغيّر شيء فعلياً ولا
 * يُميَّز عرضٌ حُوِّل عن عرضٍ لم يُحوَّل بعد إلا بعمود `converted_to_po`
 * المنطقي وحده. نفس نمط `Convert to Quotation` على `purchase_orders.status`.
 *
 * الصفوف القائمة لا تتغيّر: التعداد يتّسع فحسب — نفس نمط
 * `2026_08_29_100003_add_closed_cancelled_status_to_purchase_invoices_table.php`.
 */
return new class extends Migration
{
    private const STATUSES = "'disagree','approval','Under Review','Convert to Order'";

    private const STATUSES_BEFORE = "'disagree','approval','Under Review'";

    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('purchase_quotations_view')) {
            DB::statement('ALTER TABLE `purchase_quotations_view` MODIFY `status` ENUM('.self::STATUSES.") NULL DEFAULT 'Under Review'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('purchase_quotations_view')) {
            DB::table('purchase_quotations_view')->where('status', 'Convert to Order')->update(['status' => 'approval']);

            DB::statement('ALTER TABLE `purchase_quotations_view` MODIFY `status` ENUM('.self::STATUSES_BEFORE.") NULL DEFAULT 'Under Review'");
        }
    }
};
