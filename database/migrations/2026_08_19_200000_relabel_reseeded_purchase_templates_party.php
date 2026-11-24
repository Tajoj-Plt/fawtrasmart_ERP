<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة تسمية طرف مستندات المشتريات إلى «المورّد» — مرّةً أخرى.
 *
 * هجرة `..._170000_relabel_purchase_templates_party_as_supplier` فعلت هذا
 * فعلاً، لكن `DocumentTemplateSeeder` كان يكتب «العميل» في نصّ القالب
 * الجاهز لكل أنواع مجموعة الفواتير — بما فيها فاتورة ومرتجع المشتريات.
 * وهو يزرع بـ`updateOrCreate`، فأيّ تشغيل تالٍ للبذور يدهس ما أصلحته
 * الهجرة ويعيد «العميل» إلى المطبوعة.
 *
 * صار البذّار يقرأ تسمية الطرف من `types()` (`party`)، فالمصدر مُصلَح؛
 * وهذه الهجرة تُصلح الصفوف التي أعاد البذّار كتابتها قبل ذلك.
 *
 * **ما لا يُمَسّ:** رموز الدمج `{{customer.name}}` وأخواتها — المُصيِّر
 * يقرأها بهذا الاسم، وتغييرها يُفرغ الخانة. المستبدَل هو النصّ المعروض
 * وحده. والاستبدال لا يطال إلا أنواع المشتريات، فقوالب المبيعات سليمة.
 */
return new class extends Migration
{
    protected const TYPES = [
        'purchase_invoice',
        'purchase_return',
        'purchase_order',
        'quotation_request',
        'purchase_quotation',
        'purchase_order_request',
    ];

    /** النصّ المعروض قبل ← بعد. الأطول أولاً فلا يبتلعه الأقصر */
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
        $this->rewrite(self::LABELS);
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::LABELS));
    }

    /**
     * @param  array<string, string>  $map
     */
    protected function rewrite(array $map): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->whereIn('document_type', self::TYPES)
            ->orderBy('id')
            ->chunkById(100, function ($templates) use ($map) {
                foreach ($templates as $template) {
                    $content = strtr((string) $template->content, $map);

                    if ($content === (string) $template->content) {
                        continue;
                    }

                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update(['content' => $content, 'updated_at' => now()]);
                }
            });
    }
};
