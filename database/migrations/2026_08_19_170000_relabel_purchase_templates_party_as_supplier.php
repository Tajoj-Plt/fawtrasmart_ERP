<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * في مطبوعات دورة المشتريات: «العميل» ← «المورّد».
 *
 * قوالب هذه الأنواع منسوخة عن قوالب المبيعات، فترويسة الطرف الثاني فيها
 * مكتوبة «بيانات العميل / الطرف الثاني (Customer Details)» — وهو خطأ في
 * مستند شراء: الطرف الآخر مورّد لا عميل.
 *
 * **ما لا يُمَسّ:** رموز الدمج `{{customer.name}}` وأخواتها تبقى كما هي —
 * المُصيِّر يقرأها بهذا الاسم، وتغييرها يُفرغ الخانة. المستبدَل هو النصّ
 * المعروض وحده: العربي، و«Customer» بحرف كبير (فلا يلتبس بالرمز الصغير).
 *
 * الأنواع المشمولة أربعة فقط، فقوالب المبيعات لا تتأثّر.
 */
return new class extends Migration
{
    protected const TYPES = [
        'purchase_invoice',
        'purchase_order',
        'quotation_request',
        'purchase_quotation',
        'purchase_order_request',
    ];

    /** النصّ المعروض قبل ← بعد */
    protected const LABELS = [
        'بيانات العميل' => 'بيانات المورّد',
        'معلومات العميل' => 'معلومات المورّد',
        'اسم العميل' => 'اسم المورّد',
        'رقم العميل' => 'رقم المورّد',
        'كود العميل' => 'كود المورّد',
        'هاتف العميل' => 'هاتف المورّد',
        'عنوان العميل' => 'عنوان المورّد',
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
