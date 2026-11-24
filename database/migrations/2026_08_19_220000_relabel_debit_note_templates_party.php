<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قوالب «الإشعار المدين»: «العميل» ← «المورّد».
 *
 * الإشعار المدين في هذا النظام مستند مشتريات — تُصدره شاشة «الإشعارات
 * المدينة» للمورّد، ويُحفظ في `purchase_invoices` بنوع `City Notice`.
 * لكن قوالبه مزروعة من مجموعة الفواتير العامّة فترويسة طرفه «بيانات
 * العميل»، فتخرج المطبوعة منسوبةً إلى عميل لا وجود له.
 *
 * البذّار أُصلح ليقرأ تسمية الطرف من `types()`، وهذه الهجرة تُصلح الصفوف
 * المزروعة قبله. رموز الدمج `{{customer.name}}` لا تُمَسّ — المُصيِّر
 * يقرأها بهذا الاسم.
 */
return new class extends Migration
{
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
            ->where('document_type', 'debit_note')
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
