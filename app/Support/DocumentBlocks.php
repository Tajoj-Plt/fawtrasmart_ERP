<?php

namespace App\Support;

use App\Services\DocumentTemplateMarkup;

/**
 * كتل جاهزة تُدرج في قالب المستند بنقرة واحدة.
 *
 * بناء ترويسة أو جدول أصناف حقلاً حقلاً يتطلّب من المستخدم معرفة أي حقل
 * يوضع في أي عمود، وأي صف هو المتكرر. الكتلة تُدرج التركيبة كاملة وصحيحة،
 * فيبقى عليه التنسيق فقط.
 *
 * المخرجات بصيغة **المحرّر** (شرائح + `data-repeat`) لا بصيغة التخزين،
 * لأنها تُدرج داخل المحرّر المرئي ويحوّلها `DocumentTemplateMarkup` عند الحفظ.
 */
class DocumentBlocks
{
    /**
     * الكتل بتسمياتها وتلميحاتها المترجَمة.
     *
     * النصوص العربية أدناه احتياطٌ يظهر إن نقصت الترجمة — أما `html` فبنية
     * لا نصّ واجهة، ويبقى كما هو.
     *
     * @return array<string, array{label: string, icon: string, hint: string, html: string}>
     */
    public static function all(): array
    {
        $blocks = self::definitions();

        foreach ($blocks as $key => $block) {
            $blocks[$key]['label'] = self::tr($key.'.label', $block['label']);
            $blocks[$key]['hint'] = self::tr($key.'.hint', $block['hint']);
        }

        return $blocks;
    }

    private static function tr(string $key, string $fallback): string
    {
        $full = 'settings.doc_templates.blocks.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    /**
     * @return array<string, array{label: string, icon: string, hint: string, html: string}>
     */
    private static function definitions(): array
    {
        return [
            'business_header' => [
                'label' => 'ترويسة المنشأة',
                'icon' => 'home',
                'hint' => 'الاسم والعنوان والرقم الضريبي مع الشعار',
                'html' => self::businessHeader(),
            ],
            'document_title' => [
                'label' => 'عنوان المستند',
                'icon' => 'type',
                'hint' => 'اسم المستند ورقمه وتاريخه',
                'html' => self::documentTitle(),
            ],
            'customer_box' => [
                'label' => 'بيانات العميل',
                'icon' => 'user',
                'hint' => 'جدول العميل وبيانات المستند',
                'html' => self::customerBox(),
            ],
            'items_table' => [
                'label' => 'جدول الأصناف — كامل',
                'icon' => 'grid',
                'hint' => 'ستة أعمدة مع صف يتكرر لكل صنف',
                'html' => self::itemsTable(),
            ],
            'items_table_simple' => [
                'label' => 'جدول الأصناف — مبسّط',
                'icon' => 'list',
                'hint' => 'ثلاثة أعمدة، مناسب للطابعة الحرارية',
                'html' => self::itemsTableSimple(),
            ],
            'totals_box' => [
                'label' => 'صندوق الإجماليات',
                'icon' => 'dollar-sign',
                'hint' => 'المجموع والخصم والضريبة والإجمالي',
                'html' => self::totalsBox(),
            ],
            'qr_notes' => [
                'label' => 'الملاحظات ورمز QR',
                'icon' => 'maximize',
                'hint' => 'شروط المستند مع رمز الاستجابة السريعة',
                'html' => self::qrNotes(),
            ],
            'signatures' => [
                'label' => 'التوقيع والختم',
                'icon' => 'edit-2',
                'hint' => 'خانتا توقيع في تذييل المستند',
                'html' => self::signatures(),
            ],
        ];
    }

    // ==================== الكتل ====================

    protected static function businessHeader(): string
    {
        return '<table style="width:100%;border-collapse:collapse;margin-bottom:12px">'
            .'<tbody><tr>'
            .'<td style="border:0;vertical-align:top">'
            .'<div style="font-size:1.45em;font-weight:700">'.self::chip('business.name').'</div>'
            .'<div>'.self::chip('business.address').'</div>'
            .'<div>'.self::chip('business.phone').' — '.self::chip('business.email').'</div>'
            .'<div>الرقم الضريبي: '.self::chip('business.tax_number').'</div>'
            .'<div>السجل التجاري: '.self::chip('business.commercial_registration').'</div>'
            .'</td>'
            // الصنف `company-logo` هو ما يخفيه مفتاح «إظهار الشعار» ويقيسه
            // خيار ارتفاع الشعار — بدونه يظهر الشعار مهما أُطفئ المفتاح
            .'<td style="border:0;width:30%;text-align:left;vertical-align:top">'
            .'<img src="{{business.logo}}" class="company-logo" alt="شعار المنشأة" style="max-width:100%">'
            .'</td>'
            .'</tr></tbody></table>';
    }

    protected static function documentTitle(): string
    {
        return '<div style="text-align:center;margin:14px 0">'
            .'<div style="font-size:1.3em;font-weight:700">'.self::chip('document.title').'</div>'
            .'<div style="font-size:.9em">رقم '.self::chip('document.number')
            .' — بتاريخ '.self::chip('document.date').'</div>'
            .'</div>';
    }

    protected static function customerBox(): string
    {
        return '<table style="width:100%;border-collapse:collapse;margin-bottom:12px">'
            .'<tbody>'
            .'<tr>'
            .'<th style="width:18%;text-align:start">العميل</th><td>'.self::chip('customer.name').'</td>'
            .'<th style="width:18%;text-align:start">التاريخ</th><td>'.self::chip('document.date').'</td>'
            .'</tr>'
            .'<tr>'
            .'<th style="text-align:start">الرقم الضريبي</th><td>'.self::chip('customer.tax_number').'</td>'
            .'<th style="text-align:start">تاريخ الاستحقاق</th><td>'.self::chip('document.due_date').'</td>'
            .'</tr>'
            .'<tr>'
            .'<th style="text-align:start">الهاتف</th><td>'.self::chip('customer.phone').'</td>'
            .'<th style="text-align:start">طريقة الدفع</th><td>'.self::chip('document.payment_method').'</td>'
            .'</tr>'
            .'<tr>'
            .'<th style="text-align:start">العنوان</th><td colspan="3">'.self::chip('customer.address').'</td>'
            .'</tr>'
            .'</tbody></table>';
    }

    protected static function itemsTable(): string
    {
        return '<table style="width:100%;border-collapse:collapse;margin-bottom:12px">'
            .'<thead><tr>'
            .'<th>الصنف</th><th>الكمية</th><th>الوحدة</th>'
            .'<th>سعر الوحدة</th><th>الخصم</th><th>الضريبة</th><th>الإجمالي</th>'
            .'</tr></thead>'
            .'<tbody><tr data-repeat="items">'
            .'<td>'.self::chip('name', true).'</td>'
            .'<td>'.self::chip('quantity', true).'</td>'
            .'<td>'.self::chip('unit', true).'</td>'
            .'<td>'.self::chip('price', true).'</td>'
            .'<td>'.self::chip('discount', true).'</td>'
            .'<td>'.self::chip('tax', true).'</td>'
            .'<td>'.self::chip('total', true).'</td>'
            .'</tr></tbody></table>';
    }

    protected static function itemsTableSimple(): string
    {
        return '<table style="width:100%;border-collapse:collapse;margin-bottom:10px">'
            .'<thead><tr><th>الصنف</th><th>الكمية</th><th>الإجمالي</th></tr></thead>'
            .'<tbody><tr data-repeat="items">'
            .'<td>'.self::chip('name', true).'</td>'
            .'<td>'.self::chip('quantity', true).'</td>'
            .'<td>'.self::chip('total', true).'</td>'
            .'</tr></tbody></table>';
    }

    protected static function totalsBox(): string
    {
        $row = fn (string $label, string $field, bool $strong = false): string => '<tr>'
            .'<td style="text-align:start">'.($strong ? '<strong>'.$label.'</strong>' : $label).'</td>'
            .'<td style="text-align:end">'.($strong ? '<strong>'.self::chip($field).'</strong>' : self::chip($field)).'</td>'
            .'</tr>';

        return '<table style="width:100%;max-width:70mm;margin-inline-start:auto;border-collapse:collapse;margin-bottom:12px">'
            .'<tbody>'
            .$row('المجموع قبل الضريبة', 'totals.subtotal')
            .$row('الخصم', 'totals.discount')
            .$row('الشحن', 'totals.shipping')
            .$row('الضريبة', 'totals.tax')
            .$row('الإجمالي النهائي', 'totals.grand_total', true)
            .$row('المدفوع', 'totals.paid')
            .$row('مرتجع', 'totals.returned')
            .$row('المتبقي', 'totals.remaining')
            .'</tbody></table>';
    }

    protected static function qrNotes(): string
    {
        return '<table style="width:100%;border-collapse:collapse;margin-top:12px">'
            .'<tbody><tr>'
            .'<td style="border:0;vertical-align:top">'.self::chip('document.notes').'</td>'
            // `qr-box` هو الصنف الذي يخفيه مفتاح «إظهار رمز QR» ويقيسه خيار المقاس
            .'<td style="border:0;width:30mm;text-align:center">'
            .'<img src="{{document.qr_code}}" class="qr-box" alt="رمز QR">'
            .'</td>'
            .'</tr></tbody></table>';
    }

    protected static function signatures(): string
    {
        $cell = fn (string $label): string => '<td style="border:0;text-align:center;padding-top:8px">'
            .$label
            .'<div style="margin-top:40px;border-top:1px solid #999"></div>'
            .'</td>';

        return '<table style="width:100%;border-collapse:collapse;margin-top:24px">'
            .'<tbody><tr>'
            .$cell('توقيع المستلم')
            .$cell('ختم وتوقيع المنشأة')
            .'</tr></tbody></table>';
    }

    // ==================== أدوات ====================

    /** الشريحة بنفس صيغة المحرّر — مصدرها واحد حتى لا تختلف الصيغتان */
    protected static function chip(string $key, bool $isItem = false): string
    {
        return DocumentTemplateMarkup::chipHtml($key, $isItem);
    }
}
