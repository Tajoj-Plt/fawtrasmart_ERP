<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تصحيح ترحيل سابق لم يفعل شيئاً فعلياً: `2026_08_19_120000` و`2026_08_19_150000`
 * كانا يستنسخان قوالب فاتورة المشتريات (`purchase_invoice`) إلى أربعة أنواع أخرى
 * (أمر شراء، طلب شراء، طلب عرض أسعار، عرض سعر مشتريات) — لكنهما رُحِّلا **قبل**
 * أن يزرع `DocumentTemplateSeeder` (سيدر لا ترحيل) العشرة قوالب المصدر، فوجدا
 * `purchase_invoice` فارغاً وقت التنفيذ وانسحبا بلا أثر. النتيجة: قائمة
 * «المطبوعات» في شاشات أمر الشراء وطلب الشراء وطلبات عروض الأسعار وعروض أسعار
 * الموردين خالية دائماً من أي قالب مصمَّم — بخلاف شاشة فاتورة المشتريات التي
 * تعمل لأن قوالبها هي المصدر نفسه.
 *
 * تأكّدتُ مباشرة عبر الاستعلام: `purchase_invoice` يحمل 10 قوالب فعّالة اليوم،
 * والأنواع الأربعة صفر تماماً.
 *
 * كذلك ترحيلا `2026_08_19_170000` و`2026_08_19_200000` (استبدال «العميل» ←
 * «المورّد» في نصّ القوالب) "رُحِّلا" أيضاً بلا أثر لنفس السبب — ولا يزال نصّ
 * قوالب `purchase_invoice` نفسها يحمل «Customer» حتى الآن (تأكّدتُ عبر
 * الاستعلام). هذا الترحيل يطبّق الإصلاحين معاً دفعة واحدة: النسخ ثم إعادة
 * التسمية، على كل أنواع دورة المشتريات.
 *
 * غير مُتلِف: النسخ يتخطّى أي نوع له قالب واحد أصلاً، وإعادة التسمية تتخطّى
 * أي صفّ لا يطابق نصّه.
 */
return new class extends Migration
{
    protected const SOURCE_TYPE = 'purchase_invoice';

    /** النوع => بادئة الاسم المعروض — نفس التسميات المؤكَّدة مع المستخدم */
    protected const TARGETS = [
        'purchase_order' => 'طلب شراء',
        'purchase_order_request' => 'أمر شراء',
        'quotation_request' => 'طلب عرض أسعار',
        'purchase_quotation' => 'عرض سعر مشتريات',
    ];

    /** النصّ المعروض قبل ← بعد — نفس خريطة الترحيلين 170000/200000 */
    protected const LABELS = [
        'بيانات العميل' => 'بيانات المورّد',
        'معلومات العميل' => 'معلومات المورّد',
        'اسم العميل' => 'اسم المورّد',
        'رقم العميل' => 'رقم المورّد',
        'كود العميل' => 'كود المورّد',
        'هاتف العميل' => 'هاتف المورّد',
        'عنوان العميل' => 'عنوان المورّد',
        'موافقة العميل' => 'موافقة المورّد',
        'العميل' => 'المورّد',
        'Customer Details' => 'Supplier Details',
        'Customer Name' => 'Supplier Name',
        'Customer' => 'Supplier',
        'Client Details' => 'Supplier Details',
        'Client' => 'Supplier',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $this->seedMissingTypes();
        $this->relabelPartyToSupplier();
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

    protected function seedMissingTypes(): void
    {
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

                $paper = $source->paper_size ?? 'A4';
                $row['is_default'] = isset($defaultAssigned[$paper]) ? 0 : 1;
                $defaultAssigned[$paper] = true;

                $row['is_active'] = 1;
                $row['created_at'] = now();
                $row['updated_at'] = now();

                $rows[] = $row;
            }

            foreach (array_chunk($rows, 20) as $chunk) {
                DB::table('document_templates')->insert($chunk);
            }
        }
    }

    protected function relabelPartyToSupplier(): void
    {
        $types = array_merge(
            [self::SOURCE_TYPE, 'purchase_return'],
            array_keys(self::TARGETS),
        );

        DB::table('document_templates')
            ->whereIn('document_type', $types)
            ->orderBy('id')
            ->chunkById(100, function ($templates) {
                foreach ($templates as $template) {
                    $content = strtr((string) $template->content, self::LABELS);

                    if ($content === (string) $template->content) {
                        continue;
                    }

                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update(['content' => $content, 'updated_at' => now()]);
                }
            });
    }

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
