<?php

use App\Support\DocumentSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * عدّاد ترقيم مستقلّ لكل نوع مستند مشتريات.
 *
 * **المسألة:** أربعة أنواع تُحفظ في جدول واحد (`purchase_invoices`) —
 * فاتورة الشراء، ومرتجع المشتريات، وأمر الشراء، والإشعار المدين. وكان
 * `DocumentNumberService::lastNumberFromTable()` يبدأ عدّاد أي قسم جديد من
 * `max(id)` للجدول كلّه، فمن أنشأ فاتورتين ثم أوّل أمر شراء رأى رقمه يبدأ
 * من 3 لا من 1. الخدمة صارت تقرأ أرقام مستندات القسم وحده (`scope`).
 *
 * **وهذه الهجرة** تُنشئ صفوف الإعدادات الناقصة لتلك الأقسام — كانت تُنشأ
 * ضمناً عند أول توليد بقيمة البداية الخاطئة — فيبدأ كلٌّ منها من رقمه هو.
 * القسم الذي له صفٌّ محفوظ لا يُمَسّ: العدّاد الذي ضبطه المسؤول أو استهلكه
 * النظام يبقى كما هو.
 *
 * الأرقام المحجوزة أصلاً لا تُصادَم: `firstFreeNumber` يتخطّى كل رقم مأخوذ
 * في الجدول، فلو بقي `POR-000003` من الترقيم القديم قُفز عنه.
 */
return new class extends Migration
{
    /** أقسام المشتريات التي تتشارك جدول المستندات الموحّد */
    protected const SECTIONS = [
        'purchase-invoice',
        'purchase-return',
        'purchase-order-request',
        'purchase-debit-note',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('serial_settings')) {
            return;
        }

        $service = app(App\Services\DocumentNumberService::class);

        foreach (self::SECTIONS as $section) {
            $exists = DB::table('serial_settings')->where('section', $section)->exists();

            if ($exists) {
                continue;
            }

            DB::table('serial_settings')->insertOrIgnore([
                'section' => $section,
                // آخر رقم استعمله هذا النوع وحده — لا الجدول المشترك كلّه
                'current_number' => $service->lastNumberFromTable($section),
                'number_of_digits' => 5,
                'prefix' => DocumentSections::suggestedPrefix($section).'-',
                'separator' => '',
                'suffix' => null,
                'mode' => 0,
                'reset_yearly' => false,
                'reset_period' => 'never',
                'last_reset_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // الصفوف المُنشأة هنا لا تُحذف: حذفها يعيد التوليد الضمني بقيمة
        // البداية الخاطئة، وهو ما جاءت الهجرة لتمنعه.
    }
};
