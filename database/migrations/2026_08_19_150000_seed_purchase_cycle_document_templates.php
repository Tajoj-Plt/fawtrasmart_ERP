<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قوالب مستندات لبقيّة أقسام دورة المشتريات.
 *
 * كما في `2026_08_19_120000_seed_purchase_order_document_templates`: شاشة
 * «قوالب المستندات» تحمل عشرين قالباً لفاتورة المشتريات ولا شيء لهذه
 * الأنواع، فكانت شاشاتها تطبع بقوالب مكتوبة في الشيفرة ولا يجد المسؤول
 * قالباً يصمّمه.
 *
 * القوالب تُستنسخ من قوالب فاتورة المشتريات لأنها مبنية على رموز الدمج
 * العامّة نفسها (`business.*`، `customer.*`، `document.*`، `items`،
 * `totals.*`) — وهي ما تُخرجه مُطبِّعات `DocumentTemplateDataService` لهذه
 * الأنواع.
 *
 * الترحيل غير مُتلِف: يتخطّى أي نوع له قالب واحد أصلاً، فلا يدهس تصميماً
 * ولا يكرّر صفوفاً عند إعادة التشغيل.
 */
return new class extends Migration
{
    protected const SOURCE_TYPE = 'purchase_invoice';

    /** النوع => صدر الاسم المعروض في قائمة المطبوعات */
    protected const TARGETS = [
        'quotation_request' => 'طلب عرض أسعار',
        'purchase_quotation' => 'عرض سعر مشتريات',
        'purchase_order_request' => 'أمر شراء',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
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

        foreach (self::TARGETS as $type => $label) {
            $exists = DB::table('document_templates')->where('document_type', $type)->exists();

            if ($exists) {
                continue;
            }

            $rows = [];
            $defaultAssigned = [];

            foreach ($sources as $source) {
                $row = (array) $source;
                unset($row['id']);

                $row['document_type'] = $type;
                $row['name'] = $this->rename($source->name, $label);

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
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->whereIn('document_type', array_keys(self::TARGETS))
            ->delete();
    }

    /**
     * «فاتورة مشتريات - A4 احترافي» ← «عرض سعر مشتريات - A4 احترافي».
     */
    protected function rename(?string $name, string $label): string
    {
        $name = (string) $name;

        foreach (['فاتورة مشتريات', 'فاتورة الشراء', 'Purchase Invoice'] as $needle) {
            if (str_contains($name, $needle)) {
                return str_replace($needle, $label, $name);
            }
        }

        return $label.' - '.$name;
    }
};
