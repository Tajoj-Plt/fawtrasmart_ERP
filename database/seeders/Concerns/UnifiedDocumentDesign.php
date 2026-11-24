<?php

namespace Database\Seeders\Concerns;

/**
 * «التصميم الموحّد» — هويةٌ واحدة لكلّ مطبوعات النظام.
 *
 * صُمِّم على نموذجَين اعتمدهما صاحبُ النظام (2026-09-16): فاتورة A4
 * وإيصال حراري 80 مم. فالمستندُ A4 يتكوّن دائماً من الأجزاء نفسها وبالترتيب
 * نفسه، ويختلف من نوعٍ لآخر في المحتوى وحده:
 *
 *   ترويسة (الشعار والاسم ← وسائل التواصل)
 *   صفّ العنوان (بطاقة شكر ← العنوان ورقم المستند ← رمز QR)
 *   عمودا البيانات (بيانات المستند ← بيانات الطرف)
 *   المتن (جدول البنود، أو مبلغ السند، أو بنود العقد)
 *   الملخّص (بطاقة جانبية ← الإجماليات وشريط المبلغ الكلي)
 *   التواقيع — ثمّ التذييل (ملاحظات ← باركود ← شكر) وشريط الختام
 *
 * **الألوان** من `--primary-color` وحده، ولون المزروعة رماديٌّ داكن كما في
 * النموذج — فمنتقي الألوان في محرّر القالب يلوّن الهوية كلّها.
 *
 * **والمقاسات بـ`em`** مع `doc-ds` على الغلاف — انظر
 * `DocumentTemplateRenderer::render()`: بدونها يفرض المصيّرُ حجماً واحداً على
 * كلّ خلية فينهار التدرّج.
 *
 * **بيانات الطرف في أزواج `span` لا في جدول** — `DocumentTemplateVisibility`
 * تحذف عموداً كاملاً حين يُطفأ حقلٌ في صفّ جدولٍ بأكثر من خليتين.
 */
trait UnifiedDocumentDesign
{
    /** لاحقةُ اسم القالب — بها يُعرَف في الشاشة وفي أمر الاعتماد */
    public const UNIFIED_SUFFIX = 'التصميم الموحّد';

    /**
     * قالبا «التصميم الموحّد» لنوع مستند: A4 وحراري 80 مم (والعقدُ A4 وحده).
     *
     * @param  array<string, mixed>  $info
     * @return array<int, array<string, mixed>>
     */
    public function unifiedPresetsFor(string $documentType, array $info): array
    {
        $prefix = $info['prefix'];
        $party = $info['party'] ?? 'العميل';
        $partyEn = $party === 'العميل' ? 'Customer Details' : 'Supplier Details';

        $extra = match ($info['group']) {
            'voucher' => ['show_tax' => false, 'show_discount' => false],
            'statement' => ['show_tax' => false, 'show_discount' => false],
            'contract' => ['show_tax' => false, 'show_discount' => false],
            'hr_voucher' => ['show_tax' => false, 'show_discount' => false],
            'salary' => ['show_tax' => false],
            default => [],
        };

        [$a4, $thermal] = match ($info['group']) {
            'invoice' => [$this->uInvoiceA4(), $this->uInvoiceThermal()],
            'quote' => [$this->uQuoteA4(), $this->uQuoteThermal()],
            'voucher' => [$this->uVoucherA4(), $this->uVoucherThermal()],
            'journal' => [$this->uJournalA4(), $this->uJournalThermal()],
            'warehouse' => [$this->uWarehouseA4(), $this->uWarehouseThermal()],
            'contract' => [$this->uContractA4(), null],
            'salary' => [$this->uSalaryA4(), $this->uSalaryThermal()],
            'statement' => [$this->uStatementA4(), $this->uStatementThermal()],
            'hr_voucher' => ($info['kind'] ?? '') === 'commission_payment'
                ? [$this->uCommissionA4(), $this->uCommissionThermal()]
                : [$this->uAdvanceA4(), $this->uAdvanceThermal()],
            default => [null, null],
        };

        $fill = fn (string $html) => strtr($html, ['__PARTY__' => $party, '__PARTY_EN__' => $partyEn]);

        if (($info['kind'] ?? null) === 'sales_order') {
            $fill = fn (string $html) => $this->salesOrderWording(strtr($html, ['__PARTY__' => $party, '__PARTY_EN__' => $partyEn]));
        }

        $presets = [];

        if ($a4 !== null) {
            $presets[] = ['name' => "$prefix - A4 ".self::UNIFIED_SUFFIX, 'paper_size' => 'A4', 'is_default' => true,
                'content' => $fill($a4), 'styles' => $this->unifiedA4Styles(),
                'settings' => $this->makeSettings('#2f343a', '#6b7280', 13, $extra)];
        }

        if ($thermal !== null) {
            $presets[] = ['name' => "$prefix - حراري 80 مم ".self::UNIFIED_SUFFIX, 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $fill($thermal), 'styles' => $this->unifiedThermalStyles(),
                'settings' => $this->makeSettings('#000000', '#555555', 12, $extra)];
        }

        return $presets;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                     لبنات A4 المشتركة                       ║
    // ╚══════════════════════════════════════════════════════════════╝

    /** أيقونة خطّية بلون الهوية */
    private function uIcon(string $name): string
    {
        $paths = [
            'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
            'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/>',
            'pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
            'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
            'doc' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
            'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
            'receipt' => '<path d="M4 2v20l3-2 3 2 3-2 3 2 4-2V2l-4 2-3-2-3 2-3-2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            'heart' => '<path fill="currentColor" d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21.2l8.8-8.8a5.5 5.5 0 0 0 0-7.8z"/>',
            'box' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        ];

        return '<svg class="u-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
            .($paths[$name] ?? $paths['doc']).'</svg>';
    }

    /**
     * سطر «تسمية : قيمة». `$cond` تعبيرُ ظهورٍ بصيغة القوالب
     * (`!empty(document.time)`)، أو `true` فيُشتقّ من الرمز نفسه.
     */
    private function uKv(string $label, string $value, $cond = null, string $class = 'u-kv'): string
    {
        $row = '<div class="'.$class.'"><span class="u-k">'.$label.'</span><span class="u-v">'.$value.'</span></div>';

        if ($cond === true && preg_match('/^\{\{([a-z_]+\.[a-z_]+)\}\}$/', $value, $m)) {
            $cond = '!empty('.$m[1].')';
        }

        return is_string($cond) ? "@if($cond)\n$row\n@endif" : $row;
    }

    /** سطرٌ في قائمة الإجماليات — القيمة بخطٍّ أثقل */
    private function uTotal(string $label, string $value, ?string $cond = null, string $class = ''): string
    {
        $row = '<div class="u-trow '.$class.'"><span>'.$label.'</span><span>'.$value.'</span></div>';

        return $cond ? "@if($cond)\n$row\n@endif" : $row;
    }

    /** عنوانُ قسمٍ بأيقونة وسطرٍ إنجليزيّ */
    private function uSection(string $icon, string $title, string $en): string
    {
        return '<div class="u-sec-head">'.$this->uIcon($icon)
            .'<div><div class="u-sec-title">'.$title.'</div><div class="u-sec-en">'.$en.'</div></div></div>';
    }

    /**
     * يركّب مستند A4 من أجزائه.
     *
     * @param  array{cls:string, badge:array{0:string,1:string}, meta:array<int,string>, meta_title?:string,
     *     meta_en?:string, side?:string, body?:string, summary?:string, sigs?:array<int,string>,
     *     notes?:array<int,string>, thanks?:array{0:string,1:string}}  $o
     */
    private function uA4(array $o): string
    {
        $contact = '';

        // الهاتف والبريد والموقع بلا حرفٍ قويّ الاتجاه — تُعزَل LTR وإلّا انقلب «+966 509…»
        foreach ([['phone', 'business.phone', 'u-ltr'], ['mail', 'business.email', 'u-ltr'], ['pin', 'business.address', ''], ['globe', 'business.website', 'u-ltr']] as [$icon, $token, $dir]) {
            $contact .= "@if(!empty($token))\n<div class=\"u-contact-row\">".$this->uIcon($icon)."<span class=\"$dir\">{{{$token}}}</span></div>\n@endif\n";
        }

        $head = <<<HTML
    <div class="u-head">
        <div class="u-brand">
            @if(!empty(business.logo))
            <img src="{{business.logo}}" class="u-logo company-logo" alt="">
            @endif
            <div class="u-brand-text">
                <h1 class="u-brand-name">{{business.name}}</h1>
                @if(!empty(business.tax_number))
                <p class="u-brand-sub">الرقم الضريبي: <span class="u-ltr">{{business.tax_number}}</span></p>
                @endif
                @if(!empty(business.commercial_registration))
                <p class="u-brand-sub">السجل التجاري: <span class="u-ltr">{{business.commercial_registration}}</span></p>
                @endif
            </div>
        </div>
        <div class="u-contact">
$contact        </div>
    </div>
HTML;

        [$badgeTitle, $badgeSub] = $o['badge'];
        $badgeIcon = $this->uIcon($o['badge_icon'] ?? 'receipt');
        $docIcon = $this->uIcon('doc');

        $titleRow = <<<HTML
    <div class="u-title-row">
        <div class="u-panel u-badge">
            $badgeIcon
            <div>
                <div class="u-badge-title">$badgeTitle</div>
                <div class="u-badge-sub">$badgeSub</div>
            </div>
        </div>
        <div class="u-title">
            <h2>{{document.title}}</h2>
            @if(!empty(document.title_en))
            <div class="u-title-en"><span>{{document.title_en}}</span></div>
            @endif
            <div class="u-pill">الرقم : <span class="u-ltr">{{document.number}}</span></div>
        </div>
        <div class="u-panel u-qr-panel">
            @if(!empty(document.qr_code))
            <img src="{{document.qr_code}}" class="u-qr-img" alt="">
            <div class="u-qr-hint">امسح الرمز للتحقّق<br>من بيانات المستند</div>
            @else
            $docIcon
            <div class="u-qr-hint">تاريخ الإصدار<br><strong class="u-ltr">{{document.date}}</strong></div>
            @endif
        </div>
    </div>
HTML;

        $metaRows = implode("\n", $o['meta']);
        $metaTitle = $this->uSection('doc', $o['meta_title'] ?? 'بيانات المستند', $o['meta_en'] ?? 'Document Details');

        $info = "    <div class=\"u-info\">\n        <div class=\"u-info-col\">\n$metaTitle\n$metaRows\n        </div>\n"
            .(isset($o['side']) ? "        <div class=\"u-info-col {$o['side_cls']}\">\n{$o['side']}\n        </div>\n" : '')
            ."    </div>";

        $sigs = '';

        if (! empty($o['sigs'])) {
            $sigs = '<div class="u-sigs">';

            foreach ($o['sigs'] as $sig) {
                $sigs .= '<div class="u-sig"><div class="u-sig-label">'.$sig.'</div><div class="u-sig-line"></div><div class="u-sig-sub">الاسم والتوقيع</div></div>';
            }

            $sigs .= '</div>';
        }

        // ملاحظاتُ المستند تُكرَّر هنا إلّا حيث عرضها المتنُ بنفسه (البيان، الشروط، اسم الحساب)
        $notes = ($o['doc_notes'] ?? true) ? "@if(!empty(document.notes))\n<li>{{document.notes}}</li>\n@endif\n" : '';

        foreach ($o['notes'] ?? [] as $line) {
            $notes .= "<li>$line</li>\n";
        }

        [$thanksTitle, $thanksSub] = $o['thanks'] ?? ['شكراً لتعاملكم معنا', 'نسعد بخدمتكم دائماً'];
        $heart = $this->uIcon('heart');

        $foot = <<<HTML
    <div class="u-foot">
        <div class="u-notes">
            <div class="u-notes-title">ملاحظات:</div>
            <ul>
$notes            </ul>
        </div>
        <div class="u-barcode barcode-box">
            @if(!empty(document.barcode))
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span>{{document.number}}</span>
            @endif
        </div>
        <div class="u-foot-thanks">
            <strong>$heart <span>$thanksTitle</span></strong>
            <p>$thanksSub</p>
        </div>
    </div>
    <div class="u-stripe"></div>
HTML;

        return '<div class="doc-ds u-doc '.$o['cls']."\">\n"
            .$head."\n".$titleRow."\n".$info."\n"
            .($o['body'] ?? '')."\n"
            .($o['summary'] ?? '')."\n"
            .$sigs."\n".$foot."\n</div>";
    }

    /** بطاقة جانبية بعنوان — «تفاصيل الدفع» وأخواتها */
    private function uCard(string $icon, string $title, string $en, string $body, string $class = ''): string
    {
        return '<div class="u-card '.$class.'"><div class="u-card-head">'.$this->uIcon($icon)
            .'<div><div class="u-sec-title">'.$title.'</div><div class="u-sec-en">'.$en."</div></div></div>\n"
            ."<div class=\"u-card-body\">\n$body\n</div></div>";
    }

    /** الملخّص: بطاقةٌ جانبية على جهة البداية، والإجماليات على جهة النهاية */
    private function uSummary(string $card, string $totals, string $grandLabel, string $grandValue): string
    {
        return <<<HTML
    <div class="u-summary">
        $card
        <div class="u-totals">
$totals
            <div class="u-grand"><span>$grandLabel</span><span>$grandValue <small>{{business.currency}}</small></span></div>
        </div>
    </div>
HTML;
    }

    /**
     * جدول البنود. كلُّ عمود: [عربي، إنجليزي، خلية، صنف الخلية].
     *
     * @param  array<int, array{0:string,1:string,2:string,3?:string}>  $cols
     */
    private function uTable(array $cols, string $tfoot = '', string $before = ''): string
    {
        $head = '';
        $cells = '';

        foreach ($cols as $col) {
            $class = $col[3] ?? '';
            $head .= '<th class="'.$class.'">'.$col[0].'<small>'.$col[1].'</small></th>';
            $cells .= '<td class="'.$class.'">'.$col[2].'</td>';
        }

        return <<<HTML
    <table class="u-table">
        <thead><tr>$head</tr></thead>
        <tbody>
            $before
            {{#items}}
            <tr>$cells</tr>
            {{/items}}
        </tbody>
        $tfoot
    </table>
HTML;
    }

    /** بيانات الطرف في العمود الثاني */
    private function uParty(string $title, string $en, array $rows): string
    {
        return $this->uSection('user', $title, $en)."\n".implode("\n", $rows);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                     المستندات — A4                          ║
    // ╚══════════════════════════════════════════════════════════════╝

    /** أعمدة الأصناف التجارية: م، الصنف، الكمية، السعر، الخصم، الضريبة، الإجمالي */
    private function uTradeColumns(): array
    {
        return [
            ['م', 'No.', '{{index}}', 'u-seq'],
            ['اسم الصنف', 'Item Name', '<span class="u-item">{{name}}</span><span class="u-sub">{{code}}</span>', 'u-name'],
            ['الكمية', 'Qty', '{{quantity}}<span class="u-sub">{{unit}}</span>', 'u-num'],
            ['سعر الوحدة', 'Unit Price', '{{price}}', 'u-num'],
            ['الخصم', 'Discount', '{{discount}}', 'u-num'],
            ['الضريبة', 'VAT', '{{tax}}', 'u-num'],
            ['الإجمالي', 'Total', '{{total}}', 'u-num'],
        ];
    }

    private function uTradeParty(): string
    {
        return $this->uParty('بيانات __PARTY__', '__PARTY_EN__', [
            $this->uKv('الاسم', '{{customer.name}}'),
            $this->uKv('الرقم الضريبي', '<span class="u-ltr">{{customer.tax_number}}</span>', '!empty(customer.tax_number)'),
            $this->uKv('رقم الجوال', '<span class="u-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
            $this->uKv('العنوان', '{{customer.address}}', true),
        ]);
    }

    /** أسطر الإجماليات التجارية قبل «المبلغ الكلي» */
    private function uTradeTotals(): string
    {
        return implode("\n", [
            $this->uTotal('المجموع قبل الضريبة', '{{totals.subtotal}}'),
            $this->uTotal('الخصم', '{{totals.discount}}', 'totals.discount > 0', 'discount-row'),
            $this->uTotal('الشحن', '{{totals.shipping}}', 'totals.shipping > 0', 'shipping-row'),
            $this->uTotal('ضريبة القيمة المضافة', '{{totals.tax}}', 'totals.tax > 0', 'tax-row'),
        ]);
    }

    private function uInvoiceA4(): string
    {
        $pay = implode("\n", [
            $this->uKv('طريقة الدفع', '{{document.payment_method}}', true),
            $this->uKv('المبلغ المدفوع', '{{totals.paid}}', 'totals.paid > 0'),
            $this->uKv('مرتجع', '- {{totals.returned}}', 'totals.returned > 0'),
            $this->uKv('المتبقي', '{{totals.remaining}}', 'totals.paid > 0 || totals.returned > 0'),
            $this->uKv('تاريخ الاستحقاق', '{{document.due_date}}', true),
        ]);

        return $this->uA4([
            'cls' => 'u-invoice',
            'badge' => ['شكراً لتعاملكم معنا', '{{business.name}}'],
            'meta' => [
                $this->uKv('رقم المستند', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('التاريخ', '{{document.date}}'),
                $this->uKv('الوقت', '{{document.time}}', true),
                $this->uKv('الموظف', '{{document.employee}}', true),
                $this->uKv('الفرع', '{{document.branch}}', true),
            ],
            'side' => $this->uTradeParty(),
            'side_cls' => 'party-card',
            'body' => $this->uTable($this->uTradeColumns()),
            'summary' => $this->uSummary(
                $this->uCard('card', 'تفاصيل الدفع', 'Payment Details', $pay),
                $this->uTradeTotals(), 'المبلغ الكلي', '{{totals.grand_total}}'
            ),
            'notes' => ['يُرجى الاحتفاظ بهذا المستند للمراجعة.'],
        ]);
    }

    private function uQuoteA4(): string
    {
        $terms = "@if(!empty(document.notes))\n<p class=\"u-terms\">{{document.notes}}</p>\n@else\n<p class=\"u-muted\">لا شروط إضافية على هذا العرض.</p>\n@endif";

        return $this->uA4([
            'cls' => 'u-quote',
            'doc_notes' => false,
            'badge' => ['شكراً لاهتمامكم', '{{business.name}}'],
            'meta' => [
                $this->uKv('رقم العرض', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('التاريخ', '{{document.date}}'),
                $this->uKv('صالح حتى', '{{document.due_date}}', true),
                $this->uKv('الموظف', '{{document.employee}}', true),
            ],
            'side' => $this->uTradeParty(),
            'side_cls' => 'party-card',
            'body' => '<div class="u-notice">هذا عرض سعر تقديري وليس فاتورة — الأسعار قابلة للتغيير حسب التوفّر</div>'
                ."\n".$this->uTable($this->uTradeColumns()),
            'summary' => $this->uSummary(
                $this->uCard('doc', 'الشروط والأحكام', 'Terms & Conditions', $terms),
                $this->uTradeTotals(), 'الإجمالي المقدّر', '{{totals.grand_total}}'
            ),
            'sigs' => ['مقدّم العرض', 'موافقة __PARTY__'],
            'notes' => ['الأسعار بالعملة المحلية وتشمل ما ذُكر من ضرائب.'],
            'thanks' => ['نتطلّع لخدمتكم', '{{business.website}}'],
        ]);
    }

    private function uVoucherA4(): string
    {
        return $this->uA4([
            'cls' => 'u-voucher',
            'doc_notes' => false,
            'badge' => ['سند مالي معتمد', '{{business.name}}'],
            'badge_icon' => 'card',
            'meta' => [
                $this->uKv('رقم السند', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('التاريخ', '{{document.date}}'),
                $this->uKv('الوقت', '{{document.time}}', true),
                $this->uKv('طريقة الدفع', '{{document.payment_method}}', true),
                $this->uKv('الموظف', '{{document.employee}}', true),
            ],
            'side' => $this->uParty('بيانات الطرف', 'Party Details', [
                $this->uKv('الاسم', '{{customer.name}}'),
                $this->uKv('رقم الجوال', '<span class="u-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
                $this->uKv('العنوان', '{{customer.address}}', true),
                $this->uKv('الرمز', '{{customer.code}}', true),
            ]),
            'side_cls' => 'party-card',
            'body' => '<div class="u-amount"><span>المبلغ</span><span>{{totals.grand_total}} <small>{{business.currency}}</small></span></div>'
                ."\n@if(!empty(document.notes))\n<div class=\"u-textbox\"><strong>البيان:</strong> {{document.notes}}</div>\n@endif",
            'sigs' => ['المستلم', 'المسلّم', 'المعتمد'],
            'notes' => ['لا يُعتدّ بهذا السند إلا بتوقيع الطرفين.'],
            'thanks' => ['شكراً لتعاملكم معنا', '{{business.website}}'],
        ]);
    }

    private function uJournalA4(): string
    {
        $tfoot = '<tfoot><tr><td colspan="3">إجمالي القيد</td><td class="u-num">{{totals.subtotal}}</td><td class="u-num">{{totals.grand_total}}</td></tr></tfoot>';

        return $this->uA4([
            'cls' => 'u-journal',
            'doc_notes' => false,
            'badge' => ['قيد محاسبي', 'صادر عن {{business.name}}'],
            'meta' => [
                $this->uKv('رقم القيد', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('التاريخ', '{{document.date}}'),
                $this->uKv('العملة', '{{business.currency}}'),
                $this->uKv('الموظف', '{{document.employee}}', true),
            ],
            'side' => $this->uSection('receipt', 'وصف القيد', 'Description')
                ."\n@if(!empty(document.notes))\n<p class=\"u-terms\">{{document.notes}}</p>\n@else\n<p class=\"u-muted\">—</p>\n@endif",
            'side_cls' => '',
            'body' => $this->uTable([
                ['م', 'No.', '{{index}}', 'u-seq'],
                ['اسم الحساب', 'Account', '{{name}}', 'u-name'],
                ['رقم الحساب', 'Code', '{{code}}', 'u-num'],
                ['مدين', 'Debit', '{{price}}', 'u-num'],
                ['دائن', 'Credit', '{{tax}}', 'u-num'],
            ], $tfoot),
            'sigs' => ['أعدّ بواسطة', 'روجع بواسطة', 'اعتُمد بواسطة'],
            'notes' => ['قيدٌ محاسبيّ صادر من النظام.'],
            'thanks' => ['مستند داخلي', '{{business.name}}'],
        ]);
    }

    private function uWarehouseA4(): string
    {
        return $this->uA4([
            'cls' => 'u-warehouse',
            'badge' => ['إذن مخزني معتمد', '{{business.name}}'],
            'badge_icon' => 'box',
            'meta' => [
                $this->uKv('رقم الإذن', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('التاريخ', '{{document.date}}'),
                $this->uKv('الوقت', '{{document.time}}', true),
                $this->uKv('الموظف', '{{document.employee}}', true),
                $this->uKv('الفرع', '{{document.branch}}', true),
            ],
            'side' => $this->uParty('بيانات الطرف', 'Party Details', [
                $this->uKv('الاسم', '{{customer.name}}', true),
                $this->uKv('رقم الجوال', '<span class="u-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
                $this->uKv('العنوان', '{{customer.address}}', true),
            ]),
            'side_cls' => 'party-card',
            'body' => $this->uTable([
                ['م', 'No.', '{{index}}', 'u-seq'],
                ['اسم الصنف', 'Item Name', '{{name}}', 'u-name'],
                ['كود الصنف', 'Code', '{{code}}', 'u-num'],
                ['الوحدة', 'Unit', '{{unit}}', ''],
                ['الكمية', 'Qty', '{{quantity}}', 'u-num'],
            ]),
            'sigs' => ['أمين المستودع', 'المستلم', 'المعتمد'],
            'notes' => ['لا تُصرف البضاعة ولا تُستلم إلا بموجب هذا الإذن.'],
            'thanks' => ['مستند مخزني', '{{business.name}}'],
        ]);
    }

    private function uContractA4(): string
    {
        return $this->uA4([
            'cls' => 'u-contract',
            'doc_notes' => false,
            'badge' => ['عقد موثّق', '{{business.name}}'],
            'badge_icon' => 'doc',
            'meta' => [
                $this->uKv('رقم العقد', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('تاريخ البداية', '{{document.date}}'),
                $this->uKv('تاريخ الانتهاء', '{{document.due_date}}', true),
                $this->uKv('طريقة صرف الراتب', '{{document.payment_method}}', true),
                $this->uKv('الطرف الأول', '{{business.name}}'),
            ],
            'meta_title' => 'بيانات العقد',
            'meta_en' => 'Contract Details',
            'side' => $this->uParty('الطرف الثاني', 'Employee', [
                $this->uKv('الاسم', '{{customer.name}}'),
                $this->uKv('رقم الجوال', '<span class="u-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
                $this->uKv('العنوان', '{{customer.address}}', true),
            ]),
            'side_cls' => 'party-card',
            /* جدولُ بنود الراتب ثمّ ملخّصُه — كان المتنُ «الراتب الشهري» وحده
               (وهو الإجماليّ لا الصافي) والبنودُ نصّاً داخل الشروط. الشروطُ
               الآن من `contract.terms` بلا أسطر الراتب، فلا تتكرّر تحت الجدول. */
            'body' => $this->uTable([
                ['م', 'No.', '{{index}}', 'u-seq'],
                ['البند', 'Item', '{{name}}', 'u-name'],
                ['النوع', 'Type', '{{unit}}', ''],
                ['المبلغ', 'Amount', '{{total}}', 'u-num'],
            ]),
            'summary' => $this->uSummary(
                $this->uCard('doc', 'بنود وشروط العقد', 'Terms', "@if(!empty(contract.terms))\n<p class=\"u-terms\">{{contract.terms}}</p>\n@else\n<p class=\"u-muted\">—</p>\n@endif"),
                implode("\n", [
                    $this->uTotal('الراتب الأساسي', '{{contract.basic_salary}}'),
                    $this->uTotal('إجمالي المستحقات', '{{contract.total_additions}}'),
                    $this->uTotal('إجمالي الراتب', '{{contract.gross_salary}}'),
                    $this->uTotal('إجمالي المستقطعات', '- {{contract.total_deductions}}', '!empty(contract.has_deductions)'),
                ]),
                'صافي الراتب الشهري', '{{contract.net_salary}}'
            ),
            'sigs' => ['الطرف الأول (صاحب العمل)', 'الطرف الثاني (الموظف)'],
            'notes' => ['حُرّر هذا العقد من نسختين، بيد كلّ طرفٍ نسخةٌ للعمل بموجبها.'],
            'thanks' => ['نتمنّى لكم التوفيق', '{{business.name}}'],
        ]);
    }

    private function uSalaryA4(): string
    {
        $totals = implode("\n", [
            $this->uTotal('إجمالي المستحقات', '{{totals.subtotal}}', 'totals.subtotal > 0'),
            $this->uTotal('إجمالي الاستقطاعات', '{{totals.discount}}', 'totals.discount > 0', 'discount-row'),
        ]);

        $card = "@if(!empty(document.notes))\n<p class=\"u-terms\">{{document.notes}}</p>\n@endif\n"
            .$this->uKv('العملة', '{{business.currency}}');

        return $this->uA4([
            'cls' => 'u-salary',
            'doc_notes' => false,
            'badge' => ['وثيقة سرّية', 'تخصّ الموظف وحده'],
            'badge_icon' => 'user',
            'meta' => [
                $this->uKv('رقم الكشف', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('التاريخ', '{{document.date}}'),
                $this->uKv('طريقة الصرف', '{{document.payment_method}}', true),
            ],
            'side' => $this->uParty('بيانات الموظف', 'Employee Details', [
                $this->uKv('الاسم', '{{customer.name}}'),
                $this->uKv('رقم الجوال', '<span class="u-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
                $this->uKv('القسم / الإدارة', '{{customer.address}}', true),
            ]),
            'side_cls' => 'party-card',
            'body' => $this->uTable([
                ['م', 'No.', '{{index}}', 'u-seq'],
                ['البند', 'Item', '{{name}}', 'u-name'],
                ['المبلغ', 'Amount', '{{total}}', 'u-num'],
            ]),
            'summary' => $this->uSummary(
                $this->uCard('doc', 'ملاحظات', 'Notes', $card),
                $totals, 'صافي الراتب', '{{totals.grand_total}}'
            ),
            'sigs' => ['إدارة الموارد البشرية', 'المدير المالي', 'استلام الموظف'],
            'notes' => ['كشفٌ صادر من النظام.'],
            'thanks' => ['شكراً لجهودكم', '{{business.name}}'],
        ]);
    }

    private function uStatementA4(): string
    {
        $opening = '<tr class="u-opening"><td colspan="5">الرصيد الافتتاحي</td><td class="u-num">{{statement.opening}}</td></tr>';
        $tfoot = '<tfoot><tr><td colspan="3">إجمالي حركة الفترة</td><td class="u-num">{{statement.debit}}</td><td class="u-num">{{statement.credit}}</td><td></td></tr></tfoot>';

        $totals = implode("\n", [
            $this->uTotal('الرصيد الافتتاحي', '{{statement.opening}}'),
            $this->uTotal('إجمالي المدين', '{{statement.debit}}'),
            $this->uTotal('إجمالي الدائن', '{{statement.credit}}'),
        ]);

        $card = implode("\n", [
            $this->uKv('من تاريخ', '{{statement.from}}'),
            $this->uKv('إلى تاريخ', '{{statement.to}}'),
            $this->uKv('عدد الحركات', '{{statement.count}}'),
            $this->uKv('الحساب', '{{document.notes}}', true),
        ]);

        return $this->uA4([
            'cls' => 'u-statement',
            'doc_notes' => false,
            'badge' => ['كشف حساب معتمد', '{{business.name}}'],
            'meta' => [
                $this->uKv('رقم الحساب', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('تاريخ الإصدار', '{{document.date}}'),
                $this->uKv('من تاريخ', '{{statement.from}}'),
                $this->uKv('إلى تاريخ', '{{statement.to}}'),
            ],
            'side' => $this->uParty('بيانات __PARTY__', '__PARTY_EN__', [
                $this->uKv('الاسم', '{{customer.name}}'),
                $this->uKv('الرمز', '{{customer.code}}', true),
                $this->uKv('رقم الجوال', '<span class="u-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
                $this->uKv('الرقم الضريبي', '<span class="u-ltr">{{customer.tax_number}}</span>', '!empty(customer.tax_number)'),
            ]),
            'side_cls' => 'party-card',
            'body' => $this->uTable([
                ['التاريخ', 'Date', '{{date}}', 'u-num'],
                ['المرجع', 'Reference', '{{reference}}', ''],
                ['البيان', 'Description', '{{description}}', 'u-name'],
                ['مدين', 'Debit', '{{debit}}', 'u-num'],
                ['دائن', 'Credit', '{{credit}}', 'u-num'],
                ['الرصيد', 'Balance', '{{balance}}', 'u-num'],
            ], $tfoot, $opening),
            'summary' => $this->uSummary(
                $this->uCard('doc', 'ملخّص الكشف', 'Summary', $card),
                $totals, 'الرصيد الختامي ({{statement.direction}})', '{{statement.closing}}'
            ),
            'sigs' => ['المحاسب', 'المدير المالي'],
            'notes' => ['يُرجى مراجعة الكشف وإبلاغنا بأي ملاحظة خلال 15 يوماً.'],
            'meta_title' => 'بيانات الكشف',
            'meta_en' => 'Statement Details',
        ]);
    }

    /** بيانات الموظف في سندات الموارد البشرية */
    private function uEmployeeParty(): string
    {
        return $this->uParty('بيانات الموظف', 'Employee Details', [
            $this->uKv('الاسم', '{{customer.name}}'),
            $this->uKv('الرقم الوظيفي', '<span class="u-ltr">{{customer.code}}</span>', '!empty(customer.code)'),
            $this->uKv('القسم / الوظيفة', '{{customer.address}}', true),
            $this->uKv('رقم الجوال', '<span class="u-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
        ]);
    }

    /**
     * «سند سلفة»: المبلغ، ثمّ جدولُ السداد (قسطٌ لكلّ شهر أو دفعةٌ واحدة)،
     * ثمّ المسدَّدُ والمتبقّي. الحقولُ من `advance.*` —
     * `DocumentTemplateDataService::normalizeSalaryAdvance()`.
     */
    private function uAdvanceA4(): string
    {
        $card = "@if(!empty(document.notes))\n<p class=\"u-terms\">{{document.notes}}</p>\n@else\n<p class=\"u-muted\">—</p>\n@endif";

        return $this->uA4([
            'cls' => 'u-voucher u-advance',
            'doc_notes' => false,
            'badge' => ['سند سلفة موظف', '{{business.name}}'],
            'badge_icon' => 'card',
            'meta' => [
                $this->uKv('رقم السند', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('تاريخ الطلب', '{{advance.requested_date}}', true),
                $this->uKv('تاريخ الصرف', '{{advance.disbursed_date}}', true),
                $this->uKv('نوع السلفة', '{{advance.type}}', true),
                $this->uKv('الخزينة', '{{advance.treasury}}', true),
                $this->uKv('الحالة', '{{advance.stage}}', true),
                $this->uKv('المعتمِد', '{{advance.approver}}', true),
            ],
            'meta_title' => 'بيانات السلفة',
            'meta_en' => 'Advance Details',
            'side' => $this->uEmployeeParty(),
            'side_cls' => 'party-card',
            'body' => '<div class="u-amount"><span>مبلغ السلفة</span><span>{{advance.amount}} <small>{{business.currency}}</small></span></div>'
                ."\n".$this->uTable([
                    ['م', 'No.', '{{index}}', 'u-seq'],
                    ['الاستحقاق', 'Installment', '{{name}}', 'u-name'],
                    ['الحالة', 'Status', '{{unit}}', ''],
                    ['المبلغ', 'Amount', '{{total}}', 'u-num'],
                ]),
            'summary' => $this->uSummary(
                $this->uCard('doc', 'البيان', 'Reason', $card),
                implode("\n", [
                    $this->uTotal('مبلغ السلفة', '{{advance.amount}}'),
                    $this->uTotal('قيمة القسط', '{{advance.installment_amount}}', '!empty(advance.installment_amount)'),
                    $this->uTotal('المسدَّد', '{{advance.repaid}}', '!empty(advance.has_repaid)'),
                ]),
                'المتبقّي على الموظف', '{{advance.remaining}}'
            ),
            'sigs' => ['الموظف المستلم', 'المحاسب', 'المعتمِد'],
            'notes' => ['تُقتطع الأقساط من راتب الموظف في مواعيدها ما لم تُسدَّد نقداً.'],
            'thanks' => ['مستند داخلي', '{{business.name}}'],
        ]);
    }

    /**
     * «سند صرف عمولة»: مبلغُ العمولة والهدفُ والفترةُ وطريقةُ الصرف وحالةُ
     * القيد. الحقولُ من `commission.*` —
     * `DocumentTemplateDataService::normalizeCommissionPayment()`.
     */
    private function uCommissionA4(): string
    {
        $card = implode("\n", [
            $this->uKv('الهدف', '{{commission.target}}', true),
            $this->uKv('قيمة الهدف', '{{commission.target_value}}', true),
            $this->uKv('الفترة', '{{commission.period}}', true),
            "@if(!empty(document.notes))\n<p class=\"u-terms\">{{document.notes}}</p>\n@endif",
        ]);

        return $this->uA4([
            'cls' => 'u-voucher u-commission',
            'doc_notes' => false,
            'badge' => ['سند صرف عمولة', '{{business.name}}'],
            'badge_icon' => 'card',
            'meta' => [
                $this->uKv('رقم السند', '<span class="u-ltr">{{document.number}}</span>'),
                $this->uKv('تاريخ الصرف', '{{commission.paid_date}}', true),
                $this->uKv('نوع العمولة', '{{commission.period_type}}', true),
                $this->uKv('طريقة الصرف', '{{commission.method}}', true),
                $this->uKv('رقم القيد', '<span class="u-ltr">{{commission.journal_number}}</span>', '!empty(commission.journal_number)'),
                $this->uKv('حالة القيد', '{{commission.journal_status}}', true),
                $this->uKv('صرفها', '{{commission.settled_by}}', true),
            ],
            'meta_title' => 'بيانات الصرف',
            'meta_en' => 'Payment Details',
            'side' => $this->uEmployeeParty(),
            'side_cls' => 'party-card',
            'body' => '<div class="u-amount"><span>مبلغ العمولة</span><span>{{commission.amount}} <small>{{business.currency}}</small></span></div>'
                ."\n".$this->uTable([
                    ['م', 'No.', '{{index}}', 'u-seq'],
                    ['البيان', 'Description', '{{name}}', 'u-name'],
                    ['الفترة', 'Period', '{{unit}}', ''],
                    ['المبلغ', 'Amount', '{{total}}', 'u-num'],
                ]),
            'summary' => $this->uSummary(
                $this->uCard('doc', 'بيان العمولة', 'Commission', $card),
                $this->uTotal('إجمالي العمولة', '{{commission.amount}}'),
                'صافي المصروف', '{{commission.amount}}'
            ),
            'sigs' => ['الموظف المستلم', 'المحاسب', 'المعتمِد'],
            'notes' => ['عمولةٌ محسوبة من إنجاز الهدف وشرائحه في إعدادات الأهداف.'],
            'thanks' => ['شكراً لجهودكم', '{{business.name}}'],
        ]);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  المستندات — حراري 80 مم                    ║
    // ╚══════════════════════════════════════════════════════════════╝

    /** سطر «تسمية : قيمة» في الإيصال */
    private function rKv(string $label, string $value, $cond = null, string $class = 'r-kv'): string
    {
        $row = '<div class="'.$class.'"><span>'.$label.' :</span><span>'.$value.'</span></div>';

        if ($cond === true && preg_match('/^\{\{([a-z_]+\.[a-z_]+)\}\}$/', $value, $m)) {
            $cond = '!empty('.$m[1].')';
        }

        return is_string($cond) ? "@if($cond)\n$row\n@endif" : $row;
    }

    /**
     * يركّب إيصالاً حرارياً: ترويسة، عنوان، عمودا بيانات، متن، إجماليات، دفع، شكر ورموز.
     *
     * @param  array{meta:array<int,string>, party:array<int,string>, body:string, totals?:string,
     *     pay?:string, thanks?:array{0:string,1:string}}  $o
     */
    private function uThermal(array $o): string
    {
        $meta = implode("\n", $o['meta']);
        $party = implode("\n", $o['party']);
        $totals = isset($o['totals']) ? "<div class=\"r-cut\"></div>\n<div class=\"r-tot\">\n{$o['totals']}\n</div>" : '';
        $pay = isset($o['pay']) ? "<div class=\"r-cut\"></div>\n<div class=\"r-pay\">\n{$o['pay']}\n</div>" : '';
        [$thanks, $thanksSub] = $o['thanks'] ?? ['شكراً لتسوّقكم معنا', 'نراكم قريباً'];

        return <<<HTML
<div class="doc-ds u-rcpt">
    <div class="r-head">
        <div class="r-brand">
            <h2 class="r-name">{{business.name}}</h2>
            @if(!empty(business.address))
            <p class="r-tag">{{business.address}}</p>
            @endif
            @if(!empty(business.phone))
            <p class="r-tag u-ltr">{{business.phone}}</p>
            @endif
        </div>
        @if(!empty(business.logo))
        <img src="{{business.logo}}" class="r-logo company-logo" alt="">
        @endif
    </div>
    <div class="r-ids">
        @if(!empty(business.tax_number))
        <div class="r-id"><span>الرقم الضريبي :</span><span>{{business.tax_number}}</span></div>
        @endif
        @if(!empty(business.commercial_registration))
        <div class="r-id"><span>السجل التجاري :</span><span>{{business.commercial_registration}}</span></div>
        @endif
    </div>
    <div class="r-cut"></div>
    <h3 class="r-title">{{document.title}}</h3>
    @if(!empty(document.title_en))
    <p class="r-title-en">{{document.title_en}}</p>
    @endif
    <div class="r-meta">
        <div class="r-col">
$meta
        </div>
        <div class="r-col party-card">
$party
        </div>
    </div>
    <div class="r-cut"></div>
{$o['body']}
$totals
$pay
    <p class="r-thanks">$thanks</p>
    <p class="r-thanks-sub">$thanksSub</p>
    @if(!empty(document.qr_code))
    <div class="r-qr t-qr"><img src="{{document.qr_code}}" alt=""></div>
    <p class="r-hint">يمكنك مسح الرمز للاطلاع على تفاصيل المستند</p>
    @endif
    @if(!empty(document.barcode))
    <div class="r-barcode t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    /** جدول الإيصال — [عنوان، خلية، صنف] */
    private function rTable(array $cols, string $before = '', string $tfoot = ''): string
    {
        $head = '';
        $cells = '';

        foreach ($cols as $col) {
            $head .= '<th class="'.($col[2] ?? '').'">'.$col[0].'</th>';
            $cells .= '<td class="'.($col[2] ?? '').'">'.$col[1].'</td>';
        }

        return <<<HTML
    <table class="r-items">
        <thead><tr>$head</tr></thead>
        <tbody>
            $before
            {{#items}}
            <tr>$cells</tr>
            {{/items}}
        </tbody>
        $tfoot
    </table>
HTML;
    }

    private function rRow(string $label, string $value, ?string $cond = null, string $class = ''): string
    {
        $row = '<div class="r-row '.$class.'"><span>'.$label.'</span><span>'.$value.'</span></div>';

        return $cond ? "@if($cond)\n$row\n@endif" : $row;
    }

    private function rGrand(string $label, string $value): string
    {
        return '<div class="r-grand"><span>'.$label.'</span><span>'.$value.'</span></div>';
    }

    private function rTradeTable(): string
    {
        return $this->rTable([
            ['م', '{{index}}', 'r-seq'],
            ['اسم الصنف', '{{name}}', 'r-n'],
            ['الكمية', '{{quantity}}<span class="r-unit">{{unit}}</span>', ''],
            ['سعر الوحدة', '{{price}}', ''],
            ['الإجمالي', '{{total}}', ''],
        ]);
    }

    private function rTradeTotals(string $grandLabel): string
    {
        return implode("\n", [
            $this->rRow('المجموع قبل الضريبة', '{{totals.subtotal}}'),
            $this->rRow('خصم', '{{totals.discount}}', 'totals.discount > 0', 'discount-row'),
            $this->rRow('الشحن', '{{totals.shipping}}', 'totals.shipping > 0', 'shipping-row'),
            $this->rRow('ضريبة القيمة المضافة', '{{totals.tax}}', 'totals.tax > 0', 'tax-row'),
            $this->rGrand($grandLabel, '{{totals.grand_total}}'),
        ]);
    }

    private function rTradeParty(): array
    {
        return [
            $this->rKv('اسم __PARTY__', '{{customer.name}}'),
            $this->rKv('الرقم الضريبي', '{{customer.tax_number}}', '!empty(customer.tax_number)'),
            $this->rKv('الجوال', '{{customer.phone}}', '!empty(customer.phone)'),
        ];
    }

    private function uInvoiceThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('الرقم', '{{document.number}}'),
                $this->rKv('التاريخ', '{{document.date}}'),
                $this->rKv('الوقت', '{{document.time}}', true),
                $this->rKv('الموظف', '{{document.employee}}', true),
                $this->rKv('الفرع', '{{document.branch}}', true),
            ],
            'party' => array_merge($this->rTradeParty(), [
                $this->rKv('طريقة الدفع', '{{document.payment_method}}', true),
            ]),
            'body' => $this->rTradeTable(),
            'totals' => $this->rTradeTotals('المبلغ الكلي'),
            'pay' => implode("\n", [
                $this->rRow('طريقة الدفع', '{{document.payment_method}}', '!empty(document.payment_method)'),
                $this->rRow('المبلغ المدفوع', '{{totals.paid}}', 'totals.paid > 0'),
                $this->rRow('مرتجع', '- {{totals.returned}}', 'totals.returned > 0'),
                $this->rRow('المتبقي', '{{totals.remaining}}', 'totals.paid > 0 || totals.returned > 0'),
            ]),
        ]);
    }

    private function uQuoteThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم العرض', '{{document.number}}'),
                $this->rKv('التاريخ', '{{document.date}}'),
                $this->rKv('صالح حتى', '{{document.due_date}}', true),
            ],
            'party' => $this->rTradeParty(),
            'body' => $this->rTradeTable(),
            'totals' => $this->rTradeTotals('الإجمالي المقدّر'),
            'pay' => "@if(!empty(document.notes))\n<p class=\"r-note\">{{document.notes}}</p>\n@endif\n<p class=\"t-footer\">عرض سعر تقديري وليس فاتورة</p>",
            'thanks' => ['شكراً لاهتمامكم', 'نتطلّع لخدمتكم'],
        ]);
    }

    private function uVoucherThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم السند', '{{document.number}}'),
                $this->rKv('التاريخ', '{{document.date}}'),
                $this->rKv('الوقت', '{{document.time}}', true),
            ],
            'party' => [
                $this->rKv('الطرف', '{{customer.name}}'),
                $this->rKv('طريقة الدفع', '{{document.payment_method}}', true),
                $this->rKv('الموظف', '{{document.employee}}', true),
            ],
            'body' => "<div class=\"r-amount\">{{totals.grand_total}} <small>{{business.currency}}</small></div>\n"
                ."@if(!empty(document.notes))\n<p class=\"r-note\"><strong>البيان:</strong> {{document.notes}}</p>\n@endif",
            'pay' => '<div class="r-sigs"><span>المستلم: ...........</span><span>المسلّم: ...........</span></div>',
            'thanks' => ['شكراً لتعاملكم معنا', '{{business.name}}'],
        ]);
    }

    private function uAdvanceThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم السند', '{{document.number}}'),
                $this->rKv('التاريخ', '{{document.date}}', true),
                $this->rKv('النوع', '{{advance.type}}', true),
            ],
            'party' => [
                $this->rKv('الموظف', '{{customer.name}}'),
                $this->rKv('الرقم الوظيفي', '{{customer.code}}', true),
                $this->rKv('الخزينة', '{{advance.treasury}}', true),
            ],
            'body' => "<div class=\"r-amount\">{{advance.amount}} <small>{{business.currency}}</small></div>\n"
                .$this->rTable([
                    ['م', '{{index}}', 'r-seq'],
                    ['الاستحقاق', '{{name}}', 'r-n'],
                    ['الحالة', '{{unit}}', ''],
                    ['المبلغ', '{{total}}', ''],
                ])
                ."\n@if(!empty(document.notes))\n<p class=\"r-note\"><strong>البيان:</strong> {{document.notes}}</p>\n@endif",
            'totals' => implode("\n", [
                $this->rRow('المسدَّد', '{{advance.repaid}}', '!empty(advance.has_repaid)'),
                $this->rGrand('المتبقّي', '{{advance.remaining}}'),
            ]),
            'pay' => '<div class="r-sigs"><span>المستلم: ...........</span><span>المحاسب: ...........</span></div>',
            'thanks' => ['مستند داخلي', '{{business.name}}'],
        ]);
    }

    private function uCommissionThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم السند', '{{document.number}}'),
                $this->rKv('تاريخ الصرف', '{{commission.paid_date}}', true),
                $this->rKv('النوع', '{{commission.period_type}}', true),
            ],
            'party' => [
                $this->rKv('الموظف', '{{customer.name}}'),
                $this->rKv('الهدف', '{{commission.target}}', true),
                $this->rKv('الفترة', '{{commission.period}}', true),
            ],
            'body' => "<div class=\"r-amount\">{{commission.amount}} <small>{{business.currency}}</small></div>\n"
                ."@if(!empty(commission.method))\n<p class=\"r-note\"><strong>طريقة الصرف:</strong> {{commission.method}}</p>\n@endif\n"
                ."@if(!empty(document.notes))\n<p class=\"r-note\"><strong>البيان:</strong> {{document.notes}}</p>\n@endif",
            'pay' => '<div class="r-sigs"><span>المستلم: ...........</span><span>المحاسب: ...........</span></div>',
            'thanks' => ['شكراً لجهودكم', '{{business.name}}'],
        ]);
    }

    private function uJournalThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم القيد', '{{document.number}}'),
                $this->rKv('التاريخ', '{{document.date}}'),
            ],
            'party' => [
                $this->rKv('العملة', '{{business.currency}}'),
                $this->rKv('الموظف', '{{document.employee}}', true),
            ],
            'body' => "@if(!empty(document.notes))\n<p class=\"r-note\">{{document.notes}}</p>\n@endif\n"
                .$this->rTable([
                    ['الحساب', '{{name}}', 'r-n'],
                    ['مدين', '{{price}}', ''],
                    ['دائن', '{{tax}}', ''],
                ]),
            'totals' => implode("\n", [
                $this->rRow('إجمالي المدين', '{{totals.subtotal}}'),
                $this->rRow('إجمالي الدائن', '{{totals.grand_total}}'),
            ]),
            'thanks' => ['قيد محاسبي', '{{business.name}}'],
        ]);
    }

    private function uWarehouseThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم الإذن', '{{document.number}}'),
                $this->rKv('التاريخ', '{{document.date}}'),
                $this->rKv('الوقت', '{{document.time}}', true),
            ],
            'party' => [
                $this->rKv('الطرف', '{{customer.name}}', true),
                $this->rKv('الموظف', '{{document.employee}}', true),
                $this->rKv('الفرع', '{{document.branch}}', true),
            ],
            'body' => $this->rTable([
                ['م', '{{index}}', 'r-seq'],
                ['اسم الصنف', '{{name}}<span class="r-unit">{{code}}</span>', 'r-n'],
                ['الكمية', '{{quantity}}<span class="r-unit">{{unit}}</span>', ''],
            ]),
            'pay' => "@if(!empty(document.notes))\n<p class=\"r-note\">{{document.notes}}</p>\n@endif\n"
                .'<div class="r-sigs"><span>أمين المستودع: ........</span><span>المستلم: ........</span></div>',
            'thanks' => ['إذن مخزني', '{{business.name}}'],
        ]);
    }

    private function uSalaryThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم الكشف', '{{document.number}}'),
                $this->rKv('التاريخ', '{{document.date}}'),
            ],
            'party' => [
                $this->rKv('الموظف', '{{customer.name}}'),
                $this->rKv('القسم', '{{customer.address}}', true),
            ],
            'body' => $this->rTable([
                ['البند', '{{name}}', 'r-n'],
                ['المبلغ', '{{total}}', ''],
            ]),
            'totals' => implode("\n", [
                $this->rRow('إجمالي الاستقطاعات', '{{totals.discount}}', 'totals.discount > 0', 'discount-row'),
                $this->rGrand('صافي الراتب', '{{totals.grand_total}}'),
            ]),
            'pay' => '<div class="r-sigs"><span>توقيع الموظف: ...........</span></div>',
            'thanks' => ['شكراً لجهودكم', '{{business.name}}'],
        ]);
    }

    private function uStatementThermal(): string
    {
        return $this->uThermal([
            'meta' => [
                $this->rKv('رقم الحساب', '{{document.number}}'),
                $this->rKv('من', '{{statement.from}}'),
                $this->rKv('إلى', '{{statement.to}}'),
            ],
            'party' => [
                $this->rKv('__PARTY__', '{{customer.name}}'),
                $this->rKv('الجوال', '{{customer.phone}}', '!empty(customer.phone)'),
            ],
            'body' => $this->rTable([
                ['التاريخ', '{{date}}', ''],
                ['البيان', '{{description}}', 'r-n'],
                ['مدين', '{{debit}}', ''],
                ['دائن', '{{credit}}', ''],
            ], '<tr><td colspan="4" class="r-n">الرصيد الافتتاحي: {{statement.opening}}</td></tr>'),
            'totals' => implode("\n", [
                $this->rRow('إجمالي المدين', '{{statement.debit}}'),
                $this->rRow('إجمالي الدائن', '{{statement.credit}}'),
                $this->rGrand('الرصيد الختامي', '{{statement.closing}}'),
            ]),
            'pay' => $this->rRow('طبيعة الرصيد', '{{statement.direction}}'),
            'thanks' => ['شكراً لتعاملكم معنا', '{{business.name}}'],
        ]);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                          الأنماط                            ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function unifiedA4Styles(): string
    {
        return <<<'CSS'
/* ═════════════ التصميم الموحّد — A4 ═════════════
   المقاسات بـ`em` من الغلاف، والألوان من `--primary-color`. */
.u-doc { color: #1f2937; line-height: 1.55;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.u-doc * { box-sizing: border-box; }
.u-doc h1, .u-doc h2, .u-doc h3, .u-doc p { margin: 0; }
.u-doc table { width: 100%; border-collapse: collapse; }
.u-doc img { max-width: 100%; }
.u-ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }
.u-ico { width: 1.3em; height: 1.3em; flex: none; color: var(--primary-color); vertical-align: middle; }

/* ───── الترويسة ───── */
.u-head { display: flex; justify-content: space-between; align-items: center; gap: 1.5em;
    padding-bottom: 1em; border-bottom: 2px solid var(--primary-color); }
.u-brand { display: flex; align-items: center; gap: 1em; min-width: 0; }
.u-logo { max-height: 5.4em; max-width: 9em; object-fit: contain; }
.u-brand-name { font-size: 2em; font-weight: 800; color: var(--primary-color); line-height: 1.25; }
.u-brand-sub { font-size: .9em; color: #4b5563; margin-top: .15em; }
.u-contact { border-inline-start: 1px solid #d1d5db; padding-inline-start: 1.3em;
    display: flex; flex-direction: column; gap: .35em; font-size: .9em; min-width: 14em; }
.u-contact-row { display: flex; align-items: center; gap: .6em; }
.u-contact-row span { unicode-bidi: plaintext; }

/* ───── صفّ العنوان ───── */
.u-title-row { display: grid; grid-template-columns: 1fr 1.55fr 1fr; gap: 1em; align-items: center; margin: 1.1em 0; }
.u-panel { background: #f3f4f6; border-radius: .7em; padding: .85em 1em; min-height: 6.2em;
    display: flex; align-items: center; gap: .8em; }
.u-panel > .u-ico { width: 2.3em; height: 2.3em; }
.u-badge-title { font-weight: 800; font-size: 1.08em; color: var(--primary-color); line-height: 1.35; }
.u-badge-sub { font-size: .82em; color: #4b5563; margin-top: .25em; }
.u-title { text-align: center; }
.u-title h2 { font-size: 2em; font-weight: 800; color: var(--primary-color); line-height: 1.25; }
.u-title-en { display: flex; align-items: center; gap: .7em; margin: .2em 0 .5em;
    color: #374151; font-size: .95em; letter-spacing: .08em; text-transform: uppercase; }
.u-title-en::before, .u-title-en::after { content: ""; flex: 1; height: 1px; background: #9ca3af; }
.u-pill { display: inline-block; background: var(--primary-color); color: #fff;
    border-radius: .6em; padding: .3em 1.2em; font-weight: 700; font-size: 1.05em; }
.u-qr-panel { justify-content: center; }
.u-qr-img { width: 5.2em; height: 5.2em; flex: none; }
.u-qr-hint { font-size: .8em; color: #4b5563; line-height: 1.6; }

/* ───── عمودا البيانات ───── */
.u-info { display: grid; grid-template-columns: 1fr 1fr; padding: .3em 0 .9em;
    border-bottom: 1px solid #d1d5db; margin-bottom: 1em; }
.u-info-col { padding: 0 1.1em; }
.u-info-col:first-child { padding-inline-start: .2em; }
.u-info-col + .u-info-col { border-inline-start: 1px solid #d1d5db; }
.u-sec-head { display: flex; align-items: center; gap: .6em; margin-bottom: .55em; }
.u-sec-head .u-ico { width: 1.9em; height: 1.9em; }
.u-sec-title { font-weight: 800; font-size: 1.1em; color: var(--primary-color); line-height: 1.25; }
.u-sec-en { font-size: .78em; color: #6b7280; letter-spacing: .04em; }
.u-kv { display: flex; gap: .5em; padding: .2em 0; font-size: .93em; }
.u-k { flex: 0 0 40%; color: #374151; position: relative; padding-inline-end: 1em; white-space: nowrap; }
.u-card .u-k { flex-basis: 48%; }
.u-k::after { content: ":"; position: absolute; inset-inline-end: .3em; color: #6b7280; }
.u-v { flex: 1; font-weight: 600; color: #111827; word-break: break-word; }
.u-terms { font-size: .92em; line-height: 1.8; white-space: pre-line; }
.u-muted { color: #9ca3af; font-size: .9em; }

/* ───── جدول البنود ───── */
.u-doc .u-table { border: 1px solid #d1d5db; border-radius: .6em; overflow: hidden;
    border-collapse: separate; border-spacing: 0; margin-bottom: 1em; }
.u-table thead th { background: var(--primary-color); color: #fff; font-weight: 700;
    padding: .5em .5em; text-align: center; line-height: 1.3; white-space: nowrap; }
.u-table thead th + th { border-inline-start: 1px solid rgba(255,255,255,.2); }
.u-table th small { display: block; font-weight: 400; font-size: .72em; opacity: .85; }
.u-table tbody td { padding: .5em .6em; text-align: center; border-top: 1px solid #e5e7eb; }
.u-table tbody td + td { border-inline-start: 1px solid #e5e7eb; }
.u-table tbody tr:nth-child(even) td { background: #f6f7f8; }
.u-table td.u-name { text-align: start; }
.u-table td.u-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.u-table .u-seq { width: 3em; }
.u-item { display: block; font-weight: 600; }
.u-sub { display: block; font-size: .78em; color: #6b7280; }
.u-table tfoot td, .u-table tr.u-opening td { background: #eceef0; font-weight: 800;
    padding: .5em .6em; text-align: center; border-top: 1px solid #d1d5db; }
.u-table tfoot td + td { border-inline-start: 1px solid #d1d5db; }

/* ───── الملخّص ───── */
.u-summary { display: grid; grid-template-columns: 1fr 1.45fr; gap: 1.4em; align-items: start; margin-bottom: 1em; }
.u-card { border: 1px solid #e5e7eb; border-radius: .7em; overflow: hidden; }
.u-card-head { background: #f3f4f6; padding: .55em .9em; display: flex; gap: .6em; align-items: center; }
.u-card-head .u-ico { width: 1.7em; height: 1.7em; }
.u-card-body { padding: .6em 1em .7em; }
.u-trow { display: flex; justify-content: space-between; gap: 1em; padding: .55em .9em; border-bottom: 1px solid #e5e7eb; }
.u-trow span:last-child { font-weight: 700; font-variant-numeric: tabular-nums; }
.u-grand { display: flex; justify-content: space-between; align-items: center; gap: 1em;
    background: var(--primary-color); color: #fff; border-radius: .5em;
    padding: .65em 1em; margin-top: .5em; font-weight: 800; font-size: 1.22em; }
.u-grand small, .u-amount small { font-size: .62em; font-weight: 500; }

/* ───── السند والعقد ───── */
.u-amount { display: flex; justify-content: space-between; align-items: center;
    background: var(--primary-color); color: #fff; border-radius: .7em; padding: .9em 1.4em; margin-bottom: 1em; }
.u-amount span:first-child { font-size: 1.1em; font-weight: 700; }
.u-amount span:last-child { font-size: 1.9em; font-weight: 800; }
.u-textbox { border: 1px solid #e5e7eb; border-radius: .7em; padding: .8em 1em; background: #fafafa;
    margin-bottom: 1em; line-height: 1.9; white-space: pre-line; }
.u-notice { background: #f3f4f6; border-inline-start: 4px solid var(--primary-color);
    padding: .5em .9em; border-radius: .4em; margin-bottom: 1em; font-size: .92em; }

/* ───── التواقيع ───── */
.u-sigs { display: flex; gap: 2em; margin: 1.3em 0 .6em; page-break-inside: avoid; }
.u-sig { flex: 1; text-align: center; }
.u-sig-label { font-weight: 700; font-size: .95em; }
.u-sig-line { border-bottom: 1px solid #6b7280; height: 2.6em; margin: 0 1em; }
.u-sig-sub { font-size: .76em; color: #6b7280; margin-top: .3em; }

/* ───── التذييل ───── */
.u-foot { display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 1.2em; align-items: center;
    border-top: 1.5px dashed #9ca3af; padding-top: .9em; margin-top: .6em; page-break-inside: avoid; }
.u-notes-title { font-weight: 800; margin-bottom: .2em; }
.u-notes ul { margin: 0; padding-inline-start: 1.1em; font-size: .82em; color: #374151; line-height: 1.8; }
.u-barcode { text-align: center; }
.u-barcode img { height: 3.4em; max-width: 100%; }
.u-barcode span { display: block; letter-spacing: .2em; font-size: .95em; direction: ltr; }
.u-foot-thanks { text-align: center; }
.u-foot-thanks strong { display: flex; align-items: center; justify-content: center; gap: .4em; font-size: 1.05em; }
.u-foot-thanks p { font-size: .82em; color: #4b5563; }
.u-stripe { height: .75em; margin-top: 1.1em; border-radius: .2em;
    background: linear-gradient(100deg, var(--primary-color) 0 30%, #fff 30% 31.5%, #d1d5db 31.5% 100%); }
CSS;
    }

    private function unifiedThermalStyles(): string
    {
        return <<<'CSS'
/* ═════════════ التصميم الموحّد — إيصال حراري ═════════════ */
.u-rcpt { color: #000; line-height: 1.5; text-align: start;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.u-rcpt p, .u-rcpt h2, .u-rcpt h3 { margin: 0; }
.u-rcpt table { width: 100%; border-collapse: collapse; }
.u-rcpt .u-ltr { direction: ltr; unicode-bidi: isolate; }
.r-head { display: flex; justify-content: space-between; align-items: center; gap: .5em; }
.r-brand { min-width: 0; }
.r-name { font-size: 1.35em; font-weight: 800; line-height: 1.3; }
.r-tag { font-size: .78em; line-height: 1.5; }
.r-logo { max-height: 3.8em; max-width: 5.5em; object-fit: contain; flex: none; }
.r-ids { margin-top: .4em; font-size: .82em; }
.r-id { display: flex; justify-content: center; gap: .6em; }
.r-id span:last-child { direction: ltr; }
.r-cut { border-top: 1px dashed #555; margin: .5em 0; }
.r-title { text-align: center; font-size: 1.3em; font-weight: 800; line-height: 1.35; }
.r-title-en { text-align: center; font-size: .82em; margin-bottom: .3em; }
.r-meta { display: flex; gap: .5em; font-size: .76em; }
.r-col { flex: 1; min-width: 0; }
.r-kv { display: flex; justify-content: space-between; gap: .3em; padding: .1em 0; }
.r-kv span:first-child { white-space: nowrap; }
.r-kv span:last-child { text-align: end; word-break: break-word; }
.r-items { font-size: .78em; }
.r-items th { background: #e5e7eb; font-weight: 800; padding: .35em .2em; white-space: nowrap; text-align: center; }
.r-items td { padding: .35em .2em; border-bottom: 1px solid #e5e7eb; text-align: center; vertical-align: top; }
.r-items .r-n { text-align: start; }
.r-items .r-seq { width: 1.6em; }
.r-unit { display: block; font-size: .8em; color: #555; }
.r-tot, .r-pay { font-size: .88em; }
.r-row { display: flex; justify-content: space-between; gap: .5em; padding: .12em 0; }
.r-grand { display: flex; justify-content: space-between; gap: .5em; font-weight: 800; font-size: 1.28em;
    border-top: 1.5px solid #000; padding-top: .3em; margin-top: .35em; }
.r-amount { text-align: center; font-size: 1.6em; font-weight: 800; border: 1.5px solid #000;
    border-radius: .3em; padding: .2em; margin: .2em 0 .4em; }
.r-amount small { font-size: .5em; font-weight: 500; }
.r-note { font-size: .82em; line-height: 1.6; margin: .2em 0; }
.r-sigs { display: flex; justify-content: space-between; gap: .5em; font-size: .8em; margin: .6em 0 .2em; }
.u-rcpt .t-footer { text-align: center; font-size: .8em; }
.r-thanks { text-align: center; font-weight: 800; font-size: 1.05em; margin-top: .5em; }
.r-thanks-sub { text-align: center; font-size: .82em; }
.r-qr { text-align: center; margin: .4em 0 .1em; }
.r-qr img { width: 6.5em; height: 6.5em; }
.r-hint { text-align: center; font-size: .7em; }
.r-barcode { text-align: center; margin-top: .5em; }
.r-barcode img { height: 3em; max-width: 100%; }
.r-barcode span { display: block; letter-spacing: .15em; direction: ltr; font-size: .9em; }
CSS;
    }
}
