<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `invoices.reference_id` على مرتجعات المبيعات — الفاتورة الأصلية.
 *
 * `ReturnInvoiceController::store()` كان يكتب `$invoice_refrence->id ?? null`
 * والمتغيّر لا يُعرَّف إلّا بعد الإنشاء بمئتي سطر، فالعمود فارغٌ في كل مرتجع.
 * و`Invoice::relatedDocuments()`/`originalInvoice()` وفلترا «استبعاد الفواتير
 * المرتجعة» في شاشتي السداد المجمَّع والمحفظة تقرؤه، فلا تجد شيئاً.
 *
 * الأصلُ مكتوبٌ صحيحاً في `reference_number` (معرّفٌ رقميّ)، فيُنسخ منه حيث
 * تُطابق فاتورةٌ قائمة وحدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            UPDATE invoices r
            JOIN invoices o ON o.id = CAST(r.reference_number AS UNSIGNED) AND o.type <> 'returned'
            SET r.reference_id = o.id
            WHERE r.type = 'returned'
              AND r.reference_id IS NULL
              AND r.reference_number REGEXP '^[0-9]+$'
        ");
    }

    public function down(): void
    {
        // لا رجوع: القيمة صحيحة، وحذفُها يعيد الشاشات إلى قراءة الفراغ.
    }
};
