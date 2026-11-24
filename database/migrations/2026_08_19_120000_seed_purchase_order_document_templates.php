<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قوالب مستندات لنوع «طلب شراء».
 *
 * **لماذا؟**
 *
 * شاشة «قوالب المستندات» تحمل قوالب لكل نوع تقريباً — عشرون لفاتورة
 * المشتريات وحدها — ولا شيء لـ`purchase_order`. فكانت قائمة «المطبوعات»
 * في شاشة طلب الشراء تسقط دائماً إلى القوالب المدمجة في الشيفرة، ولا يجد
 * المسؤول في الإعدادات قالباً واحداً يصمّمه لهذا القسم.
 *
 * القوالب تُستنسخ من قوالب فاتورة المشتريات لأنها مبنية على رموز الدمج
 * العامّة نفسها (`business.*`، `customer.*`، `document.*`، `items`،
 * `totals.*`) — وهي ما تُخرجه `DocumentTemplateDataService::normalizePurchaseOrder()`
 * بالضبط، فتعمل بلا تعديل.
 *
 * الترحيل غير مُتلِف: لا يعمل إن وُجد قالب واحد من هذا النوع، فلا يدهس
 * تصميماً عدّله المسؤول ولا يكرّر الصفوف عند إعادة التشغيل.
 */
return new class extends Migration
{
    protected const SOURCE_TYPE = 'purchase_invoice';

    protected const TARGET_TYPE = 'purchase_order';

    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $exists = DB::table('document_templates')
            ->where('document_type', self::TARGET_TYPE)
            ->exists();

        if ($exists) {
            return;
        }

        $sources = DB::table('document_templates')
            ->where('document_type', self::SOURCE_TYPE)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            return;
        }

        $rows = [];
        $defaultAssigned = [];

        foreach ($sources as $source) {
            $row = (array) $source;
            unset($row['id']);

            $row['document_type'] = self::TARGET_TYPE;
            $row['name'] = $this->renameForOrders($source->name);

            // قالب افتراضي واحد لكل مقاس ورق — نفس القاعدة التي يفرضها
            // موديل DocumentTemplate عند الحفظ
            $paper = $source->paper_size ?? 'A4';
            $row['is_default'] = isset($defaultAssigned[$paper]) ? 0 : 1;
            $defaultAssigned[$paper] = true;

            $row['is_active'] = 1;
            $row['created_at'] = now();
            $row['updated_at'] = now();

            $rows[] = $row;
        }

        foreach (array_chunk($rows, 20) as $chunk) {
            DB::table('document_templates')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')->where('document_type', self::TARGET_TYPE)->delete();
    }

    /**
     * «فاتورة مشتريات - A4 احترافي» ← «طلب شراء - A4 احترافي».
     *
     * الاسم يُقرأ في قائمة المطبوعات، فيُترجم صدرُه ويبقى وصفُ التصميم.
     */
    protected function renameForOrders(?string $name): string
    {
        $name = (string) $name;

        foreach (['فاتورة مشتريات', 'فاتورة الشراء', 'Purchase Invoice'] as $needle) {
            if (str_contains($name, $needle)) {
                return str_replace($needle, 'طلب شراء', $name);
            }
        }

        return 'طلب شراء - '.$name;
    }
};
