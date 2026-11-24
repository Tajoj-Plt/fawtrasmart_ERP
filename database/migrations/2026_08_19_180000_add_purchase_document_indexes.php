<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس جدول مستندات المشتريات الموحّد.
 *
 * الجدول يخدم أربعة أنواع، وكل شاشة تصفّي بـ`type` أولاً ثم بالتاريخ أو
 * الفرع. ولم يكن على `type` ولا `date` ولا `branch_id` فهرس واحد — فكل
 * قائمة تمسح الجدول كاملاً.
 *
 * الموجود أصلاً: `code` (فريد)، و`reference_id` و`supplier_id`
 * و`account_id` و`created_by` و`updated_by` (فهارس مفاتيح أجنبية).
 *
 * لا تُضاف أعمدة ولا تُغيَّر أنواعها — فهارس فقط، فلا خطر على البيانات.
 */
return new class extends Migration
{
    /** الفهرس => أعمدته */
    protected const INDEXES = [
        // تصفية القوائم: النوع أولاً ثم الترتيب بالتاريخ
        'purchase_invoices_type_date_index' => ['type', 'date'],
        // كشوف المورّد لكل نوع
        'purchase_invoices_type_supplier_index' => ['type', 'supplier_id'],
        // عزل الفروع
        'purchase_invoices_branch_id_index' => ['branch_id'],
        // متابعة المستحقّات
        'purchase_invoices_payment_status_index' => ['payment_status'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('purchase_invoices')) {
            return;
        }

        $existing = $this->existingIndexes();

        Schema::table('purchase_invoices', function (Blueprint $table) use ($existing) {
            foreach (self::INDEXES as $name => $columns) {
                if (in_array($name, $existing, true)) {
                    continue;
                }

                // العمود قد يكون غائباً في تركيب أقدم
                foreach ($columns as $column) {
                    if (! Schema::hasColumn('purchase_invoices', $column)) {
                        continue 2;
                    }
                }

                $table->index($columns, $name);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_invoices')) {
            return;
        }

        $existing = $this->existingIndexes();

        Schema::table('purchase_invoices', function (Blueprint $table) use ($existing) {
            foreach (array_keys(self::INDEXES) as $name) {
                if (in_array($name, $existing, true)) {
                    $table->dropIndex($name);
                }
            }
        });
    }

    /** @return array<int, string> */
    protected function existingIndexes(): array
    {
        // Schema::getIndexes بديلٌ محمولٌ عن SHOW INDEX الخاص بـMySQL
        return collect(Schema::getIndexes('purchase_invoices'))
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }
};
