<?php

namespace Modules\RentalManagement\Support;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Support\Collection;

/**
 * طباعة مستندات العقارات عبر «قوالب المستندات» في إعدادات النظام.
 *
 * كانت مطبوعتا العقد والإيصال ملفَّي Blade ثابتين داخل الوحدة: لا يراهما
 * محرّر القوالب، ولا يحملان شعار المنشأة ولا ألوانها ولا ما صمّمه المسؤول.
 * هذه نقطةُ الطباعة الواحدة:
 *
 *     $html = RealEstateTemplates::render(RealEstateTemplates::CONTRACT, $contract->id, request('template'));
 *
 *     return $html !== null
 *         ? response(RealEstateTemplates::page($html, 'عقد '.$contract->code))
 *         : view('rentalmanagement::realestate.contracts.print', …);   // المدمج احتياطاً
 *
 * `render()` لا ترمي أبداً: بلا قالبٍ فعّال، أو عند أيّ خطأ في القالب أو
 * البيانات، تعيد `null` وتسجّل السبب — فيسقط النداءُ إلى المطبوعة المدمجة
 * ولا يرى المستخدم صفحة خطأ مكان عقده.
 */
final class RealEstateTemplates
{
    public const CONTRACT = 'realestate_contract';

    public const RECEIPT = 'realestate_receipt';

    public const TYPES = [self::CONTRACT, self::RECEIPT];

    /** لاحقةُ اسم القوالب الجاهزة — بها تعرفها المهاجرة فلا تكرّرها */
    public const DESIGN_SUFFIX = 'تصميم العقارات';

    private const LOG = 're_template_log';

    /**
     * المستند مصيَّراً بقالبه — أو `null` فيُطبع المدمج.
     *
     * @param  int|string|DocumentTemplate|null  $template  قالبٌ بعينه (يجب أن يكون من النوع نفسِه وفعّالاً)، أو لا شيء فالافتراضيّ
     */
    public static function render(string $type, int $id, $template = null): ?string
    {
        if (! in_array($type, self::TYPES, true)) {
            return null;
        }

        try {
            $model = self::resolve($type, $template);

            if (! $model) {
                return null;
            }

            $data = app(DocumentTemplateDataService::class)->getDocumentData($type, $id);
            $html = app(DocumentTemplateRenderer::class)->render($model, $data);

            /* المصيّر لا يرمي عند قالبٍ فاسد: يعيد صندوق «تعذّر عرض القالب».
               وهو مفيدٌ في المحرّر، لكنه في الطباعة ورقةٌ فارغة بيد المستأجر —
               فيُعامَل فشلاً ويُطبع المدمج. */
            if (str_contains($html, '<strong>تعذّر عرض القالب.</strong>')) {
                throw new \RuntimeException('تعذّر تصيير قالب المستند #'.$model->id.' ('.$type.')');
            }

            return $html;
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG, 'render', $id);

            return null;
        }
    }

    /**
     * القوالب الفعّالة لنوعٍ — لقائمة «طباعة بقالب…». الافتراضيّ أوّلاً.
     *
     * @return Collection<int, DocumentTemplate>
     */
    public static function templates(string $type): Collection
    {
        return in_array($type, self::TYPES, true) ? PrintSettings::templatesFor($type) : collect();
    }

    /** هل للنوع قالبٌ فعّال يُطبع به؟ */
    public static function available(string $type): bool
    {
        return self::templates($type)->isNotEmpty();
    }

    /** صفحةُ طباعةٍ كاملة حول ناتج `render()` — شريطُ الطباعة والرجوع كبقيّة النظام. */
    public static function page(string $html, ?string $title = null, ?string $backUrl = null, bool $autoPrint = false): string
    {
        return DocumentPrintPage::wrap($html, $title, $backUrl, $autoPrint);
    }

    /** @param  int|string|DocumentTemplate|null  $template */
    private static function resolve(string $type, $template): ?DocumentTemplate
    {
        if ($template instanceof DocumentTemplate) {
            return $template->document_type === $type && $template->is_active ? $template : null;
        }

        if (is_numeric($template) && (int) $template > 0) {
            // من القوالب الظاهرة للمستخدم (فرعُه أو العامّة) — لا أيُّ معرّفٍ يُرسَل في الرابط
            return self::templates($type)->firstWhere('id', (int) $template);
        }

        return PrintSettings::defaultTemplateFor($type);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                       القوالب الجاهزة                        ║
    // ╚══════════════════════════════════════════════════════════════╝

    /**
     * القالبان الجاهزان — صفوف `document_templates` كما تزرعها المهاجرة
     * `2026_10_05_130000_seed_real_estate_document_templates`.
     *
     * وثيقةٌ رسمية لا فاتورة: ترويسة (المنشأة ← العنوان ← بيانات المستند)، ثم
     * متنُ النوع، ثم التواقيع. المقاسات بـ`em` مع `doc-ds` على الغلاف فيسري
     * عليها حجمُ الخطّ المختار، و`--primary-color`/`--secondary-color` من
     * منتقي الألوان. والحقولُ المسمّاة (`contract.*`، `payment.*`) يملؤها
     * `DocumentTemplateDataService`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function presets(): array
    {
        $settings = fn (string $primary, string $tint) => [
            'show_logo' => true, 'show_qr' => false, 'show_barcode' => false, 'show_customer' => true,
            'show_tax' => true, 'show_discount' => false, 'font_size' => 13, 'direction' => 'rtl',
            'primary_color' => $primary, 'secondary_color' => $tint, 'font_family' => 'Tajawal',
        ];

        $row = fn (string $name, string $type, string $content, array $settings) => [
            'name' => $name.' - '.self::DESIGN_SUFFIX.' A4',
            'document_type' => $type,
            'template_type' => 'ready',
            'paper_size' => 'A4',
            'width' => 210,
            'height' => 297,
            'orientation' => 'portrait',
            'content' => $content,
            'styles' => self::styles(),
            'settings' => $settings,
            'is_active' => true,
        ];

        return [
            $row('عقد إيجار عقاري', self::CONTRACT, self::contractContent(), $settings('#1e3a5f', '#eef2f7')),
            $row('إيصال دفعة إيجار', self::RECEIPT, self::receiptContent(), $settings('#0f5f4e', '#e9f4f1')),
        ];
    }

    private static function head(string $meta): string
    {
        return <<<HTML
    <div class="re-top">
        <div class="re-org">
            @if(!empty(business.logo))
            <img src="{{business.logo}}" class="re-logo company-logo" alt="">
            @endif
            <div>
                <div class="re-org-name">{{business.name}}</div>
                @if(!empty(business.commercial_registration))
                <div class="re-org-id">السجل التجاري: <span class="re-ltr">{{business.commercial_registration}}</span></div>
                @endif
                @if(!empty(business.tax_number))
                <div class="re-org-id">الرقم الضريبي: <span class="re-ltr">{{business.tax_number}}</span></div>
                @endif
                @if(!empty(business.phone))
                <div class="re-org-id">الهاتف: <span class="re-ltr">{{business.phone}}</span></div>
                @endif
            </div>
        </div>
        <div class="re-title-block">
            <div class="re-title">{{document.title}}</div>
            @if(!empty(document.title_en))
            <div class="re-title-en">{{document.title_en}}</div>
            @endif
        </div>
        <div class="re-meta">
$meta
        </div>
    </div>
    <div class="re-rule"></div>
HTML;
    }

    private static function contractContent(): string
    {
        $head = self::head(<<<'HTML'
            <div class="re-kv"><span class="re-k">رقم العقد</span><span class="re-v"><span class="re-ltr">{{contract.code}}</span></span></div>
            <div class="re-kv"><span class="re-k">تاريخ العقد</span><span class="re-v">{{document.date}}</span></div>
            @if(!empty(contract.status))
            <div class="re-kv"><span class="re-k">الحالة</span><span class="re-v">{{contract.status}}</span></div>
            @endif
HTML);

        return <<<HTML
<div class="doc-ds re-doc re-contract">
$head
    <div class="re-sec"><span class="re-sec-title">أطراف العقد</span></div>
    <div class="re-parties">
        <div class="re-party">
            <div class="re-party-head"><span>الطرف الأول</span><small>المؤجِّر</small></div>
            <div class="re-kv"><span class="re-k">الاسم</span><span class="re-v">{{contract.owner_name}}</span></div>
            @if(!empty(contract.owner_id_number))
            <div class="re-kv"><span class="re-k">رقم الهوية / السجل</span><span class="re-v"><span class="re-ltr">{{contract.owner_id_number}}</span></span></div>
            @endif
            @if(!empty(contract.owner_tax_number))
            <div class="re-kv"><span class="re-k">الرقم الضريبي</span><span class="re-v"><span class="re-ltr">{{contract.owner_tax_number}}</span></span></div>
            @endif
            @if(!empty(contract.owner_mobile))
            <div class="re-kv"><span class="re-k">الجوال</span><span class="re-v"><span class="re-ltr">{{contract.owner_mobile}}</span></span></div>
            @endif
        </div>
        <div class="re-party party-card">
            <div class="re-party-head"><span>الطرف الثاني</span><small>المستأجر</small></div>
            <div class="re-kv"><span class="re-k">الاسم</span><span class="re-v">{{contract.tenant_name}}</span></div>
            @if(!empty(contract.tenant_id_number))
            <div class="re-kv"><span class="re-k">{{contract.tenant_id_type}}</span><span class="re-v"><span class="re-ltr">{{contract.tenant_id_number}}</span></span></div>
            @endif
            @if(!empty(contract.tenant_nationality))
            <div class="re-kv"><span class="re-k">الجنسية</span><span class="re-v">{{contract.tenant_nationality}}</span></div>
            @endif
            @if(!empty(contract.tenant_mobile))
            <div class="re-kv"><span class="re-k">الجوال</span><span class="re-v"><span class="re-ltr">{{contract.tenant_mobile}}</span></span></div>
            @endif
            @if(!empty(customer.tax_number))
            <div class="re-kv"><span class="re-k">الرقم الضريبي</span><span class="re-v"><span class="re-ltr">{{customer.tax_number}}</span></span></div>
            @endif
        </div>
    </div>

    <div class="re-sec"><span class="re-sec-title">العين المؤجَّرة</span></div>
    <div class="re-grid2">
        <div class="re-kv"><span class="re-k">العقار</span><span class="re-v">{{contract.building}}</span></div>
        <div class="re-kv"><span class="re-k">الوحدة</span><span class="re-v">{{contract.unit_type}} {{contract.unit_no}}</span></div>
        @if(!empty(contract.building_address))
        <div class="re-kv"><span class="re-k">العنوان</span><span class="re-v">{{contract.building_address}}</span></div>
        @endif
        @if(!empty(contract.floor))
        <div class="re-kv"><span class="re-k">الطابق</span><span class="re-v">{{contract.floor}}</span></div>
        @endif
        @if(!empty(contract.area))
        <div class="re-kv"><span class="re-k">المساحة</span><span class="re-v">{{contract.area}} م²</span></div>
        @endif
        @if(!empty(contract.unit_usage))
        <div class="re-kv"><span class="re-k">الاستخدام</span><span class="re-v">{{contract.unit_usage}}</span></div>
        @endif
    </div>

    <div class="re-sec"><span class="re-sec-title">المدّة والقيمة</span></div>
    <div class="re-grid2">
        <div class="re-kv"><span class="re-k">نوع العقد</span><span class="re-v">{{contract.type}}</span></div>
        <div class="re-kv"><span class="re-k">المدّة</span><span class="re-v">{{contract.duration}}</span></div>
        <div class="re-kv"><span class="re-k">تاريخ البداية</span><span class="re-v">{{contract.start_date}}</span></div>
        <div class="re-kv"><span class="re-k">تاريخ النهاية</span><span class="re-v">{{contract.end_date}}</span></div>
        <div class="re-kv"><span class="re-k">دورية السداد</span><span class="re-v">{{contract.payment_frequency}}</span></div>
        <div class="re-kv"><span class="re-k">عدد الأقساط</span><span class="re-v">{{contract.installments_count}}</span></div>
    </div>
    <table class="re-table re-values">
        <tbody>
            <tr><td class="re-name">القيمة الإيجارية السنوية</td><td class="re-num">{{contract.annual_value}} <small>{{contract.currency}}</small></td></tr>
            <tr><td class="re-name">إجمالي قيمة العقد قبل الضريبة</td><td class="re-num">{{contract.total_value}} <small>{{contract.currency}}</small></td></tr>
            @if(!empty(contract.has_tax))
            <tr class="tax-row"><td class="re-name">ضريبة القيمة المضافة ({{contract.tax}}) — {{contract.tax_mode}}</td><td class="re-num">{{contract.tax_amount}} <small>{{contract.currency}}</small></td></tr>
            @endif
            <tr class="re-net-row"><td class="re-name">إجمالي قيمة العقد</td><td class="re-num">{{contract.total_with_tax}} <small>{{contract.currency}}</small></td></tr>
            @if(!empty(contract.has_deposit))
            <tr><td class="re-name">مبلغ التأمين (يُردّ عند نهاية العقد وفق شروطه)</td><td class="re-num">{{contract.deposit}} <small>{{contract.currency}}</small></td></tr>
            @endif
        </tbody>
    </table>
    @if(!empty(contract.total_words))
    <div class="re-words">إجمالي قيمة العقد كتابةً: {{contract.total_words}}</div>
    @endif

    <div class="re-sec"><span class="re-sec-title">جدول الدفعات</span></div>
    <table class="re-table">
        <thead><tr><th class="re-seq">م</th><th class="re-name">البيان</th><th>تاريخ الاستحقاق</th><th class="re-num">المبلغ</th></tr></thead>
        <tbody>
            {{#items}}
            <tr><td class="re-seq">{{index}}</td><td class="re-name">{{name}}</td><td>{{item.due_date}}</td><td class="re-num">{{total}}</td></tr>
            {{/items}}
        </tbody>
    </table>

    @if(!empty(contract.has_terms))
    <div class="re-sec"><span class="re-sec-title">الشروط والأحكام</span></div>
    <div class="re-text re-pre">{{contract.terms}}</div>
    @endif

    @if(!empty(contract.notes))
    <p class="re-note"><strong>ملاحظات:</strong> {{contract.notes}}</p>
    @endif

    <p class="re-note re-conf">حُرّر هذا العقد من نسختين بيد كلّ طرفٍ نسخةٌ للعمل بموجبها، وأقرّ الطرفان بما ورد فيه.</p>
    <div class="re-sigs">
        <div class="re-sig"><div class="re-sig-title">الطرف الأول — المؤجِّر</div><div class="re-sig-name">{{contract.owner_name}}</div><div class="re-sig-line"><span>التوقيع</span></div><div class="re-sig-date">التاريخ: ......./......./..........</div></div>
        <div class="re-sig"><div class="re-sig-title">الطرف الثاني — المستأجر</div><div class="re-sig-name">{{contract.tenant_name}}</div><div class="re-sig-line"><span>التوقيع</span></div><div class="re-sig-date">التاريخ: ......./......./..........</div></div>
        <div class="re-sig"><div class="re-sig-title">إدارة الأملاك</div><div class="re-sig-name">{{business.name}}</div><div class="re-sig-line"><span>التوقيع والختم</span></div><div class="re-sig-date">التاريخ: ......./......./..........</div></div>
    </div>
    <div class="re-foot"><span>{{document.title}}</span><span class="re-ltr">{{contract.code}}</span></div>
</div>
HTML;
    }

    private static function receiptContent(): string
    {
        $head = self::head(<<<'HTML'
            <div class="re-kv"><span class="re-k">رقم الإيصال</span><span class="re-v"><span class="re-ltr">{{payment.code}}</span></span></div>
            <div class="re-kv"><span class="re-k">التاريخ</span><span class="re-v">{{payment.date}}</span></div>
            @if(!empty(payment.contract_code))
            <div class="re-kv"><span class="re-k">رقم العقد</span><span class="re-v"><span class="re-ltr">{{payment.contract_code}}</span></span></div>
            @endif
HTML);

        return <<<HTML
<div class="doc-ds re-doc re-receipt">
    @if(!empty(payment.is_cancelled))
    <div class="re-void">ملغى</div>
    @endif
$head
    <div class="re-grid2 party-card">
        <div class="re-kv"><span class="re-k">استلمنا من</span><span class="re-v">{{payment.tenant_name}}</span></div>
        @if(!empty(payment.tenant_mobile))
        <div class="re-kv"><span class="re-k">الجوال</span><span class="re-v"><span class="re-ltr">{{payment.tenant_mobile}}</span></span></div>
        @endif
        @if(!empty(payment.unit))
        <div class="re-kv"><span class="re-k">عن الوحدة</span><span class="re-v">{{payment.unit}}</span></div>
        @endif
        @if(!empty(payment.building))
        <div class="re-kv"><span class="re-k">العقار</span><span class="re-v">{{payment.building}}</span></div>
        @endif
    </div>

    <div class="re-amount">
        <div class="re-amount-label">المبلغ المستلم</div>
        <div class="re-amount-value"><span class="re-ltr">{{payment.amount}}</span> <small>{{payment.currency}}</small></div>
        @if(!empty(payment.amount_words))
        <div class="re-amount-words">{{payment.amount_words}}</div>
        @endif
    </div>

    <div class="re-grid2">
        @if(!empty(payment.method))
        <div class="re-kv"><span class="re-k">طريقة الدفع</span><span class="re-v">{{payment.method}}</span></div>
        @endif
        @if(!empty(payment.reference))
        <div class="re-kv"><span class="re-k">رقم المرجع</span><span class="re-v"><span class="re-ltr">{{payment.reference}}</span></span></div>
        @endif
        @if(!empty(payment.treasury))
        <div class="re-kv"><span class="re-k">أُودع في</span><span class="re-v">{{payment.treasury}}</span></div>
        @endif
        @if(!empty(payment.status))
        <div class="re-kv"><span class="re-k">الحالة</span><span class="re-v">{{payment.status}}</span></div>
        @endif
    </div>

    <div class="re-sec"><span class="re-sec-title">تفصيل الدفعة</span></div>
    <table class="re-table">
        <thead><tr><th class="re-seq">م</th><th class="re-name">البيان</th><th>تاريخ الاستحقاق</th><th class="re-num">المسدَّد</th></tr></thead>
        <tbody>
            {{#items}}
            <tr><td class="re-seq">{{index}}</td><td class="re-name">{{name}}</td><td>{{item.due_date}}</td><td class="re-num">{{total}}</td></tr>
            {{/items}}
        </tbody>
        <tfoot>
            @if(!empty(payment.has_tax))
            <tr><td colspan="3">الإيجار قبل الضريبة</td><td class="re-num">{{payment.rent}}</td></tr>
            <tr class="tax-row"><td colspan="3">ضريبة القيمة المضافة</td><td class="re-num">{{payment.tax_amount}}</td></tr>
            @endif
            @if(!empty(payment.has_deposit))
            <tr><td colspan="3">منه تأمين</td><td class="re-num">{{payment.deposit}}</td></tr>
            @endif
            @if(!empty(payment.has_advance))
            <tr><td colspan="3">منه دفعة مقدّمة</td><td class="re-num">{{payment.advance}}</td></tr>
            @endif
            <tr class="re-net-row"><td colspan="3">إجمالي المستلم</td><td class="re-num">{{payment.amount}} <small>{{payment.currency}}</small></td></tr>
            <tr><td colspan="3">المتبقّي على العقد بعد هذه الدفعة</td><td class="re-num">{{payment.contract_remaining}}</td></tr>
        </tfoot>
    </table>

    @if(!empty(payment.notes))
    <p class="re-note"><strong>ملاحظات:</strong> {{payment.notes}}</p>
    @endif

    <div class="re-sigs">
        <div class="re-sig"><div class="re-sig-title">المستلم</div><div class="re-sig-name">{{payment.received_by}}</div><div class="re-sig-line"><span>التوقيع</span></div></div>
        <div class="re-sig"><div class="re-sig-title">الدافع</div><div class="re-sig-name">{{payment.tenant_name}}</div><div class="re-sig-line"><span>التوقيع</span></div></div>
        <div class="re-sig"><div class="re-sig-title">الختم</div><div class="re-stamp">ختم المنشأة</div></div>
    </div>
    <div class="re-foot"><span>{{document.title}} — {{business.name}}</span><span class="re-ltr">{{payment.code}}</span></div>
</div>
HTML;
    }

    private static function styles(): string
    {
        return <<<'CSS'
/* ═════════════ تصميم العقارات — A4 ═════════════
   الألوان: --primary-color للخطوط والعناوين، --secondary-color لونٌ فاتح للخلفيات. */
.re-doc { color: #1f2937; line-height: 1.6; position: relative;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.re-doc * { box-sizing: border-box; }
.re-doc p { margin: 0; }
.re-doc table { width: 100%; border-collapse: collapse; }
.re-doc img { max-width: 100%; }
.re-ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }

/* ───── الترويسة ───── */
.re-top { display: grid; grid-template-columns: 1.25fr 1fr 1.1fr; gap: 1.2em; align-items: center;
    border-top: 3px double var(--primary-color); padding-top: .9em; }
.re-org { display: flex; align-items: center; gap: .8em; min-width: 0; }
.re-logo { max-height: 4.4em; max-width: 7em; object-fit: contain; }
.re-org-name { font-size: 1.35em; font-weight: 800; color: var(--primary-color); line-height: 1.3; }
.re-org-id { font-size: .8em; color: #4b5563; }
.re-title-block { text-align: center; }
.re-title { font-size: 1.9em; font-weight: 800; color: var(--primary-color); line-height: 1.25; }
.re-title-en { font-size: .75em; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-top: .2em; }
.re-meta { border-inline-start: 1px solid #d1d5db; padding-inline-start: 1em; font-size: .9em; }
.re-rule { height: 0; border-top: 2px solid var(--primary-color); border-bottom: 1px solid var(--primary-color);
    padding-top: 2px; margin: .8em 0 1em; }

/* ───── تسمية : قيمة ───── */
.re-kv { display: flex; gap: .5em; padding: .18em 0; }
.re-k { flex: 0 0 40%; color: #6b7280; }
.re-v { flex: 1; font-weight: 600; color: #111827; word-break: break-word; }
.re-meta .re-k { flex-basis: 45%; }
.re-grid2 { display: grid; grid-template-columns: 1fr 1fr; column-gap: 2.2em; margin-bottom: .5em; }

/* ───── الأقسام والنصوص ───── */
.re-sec { margin: 1em 0 .45em; border-bottom: 1px solid #d1d5db; padding-bottom: .25em; page-break-after: avoid; }
.re-sec-title { font-size: 1.15em; font-weight: 800; color: var(--primary-color); }
.re-text { text-align: justify; line-height: 1.95; }
.re-pre { white-space: pre-line; }
.re-words { margin: .5em 0; padding: .45em .9em; border-inline-start: 3px solid var(--primary-color);
    background: var(--secondary-color); font-weight: 600; font-size: .95em; }
.re-note { font-size: .88em; color: #374151; margin-top: .6em; line-height: 1.8; }
.re-conf { color: #6b7280; font-size: .8em; }

/* ───── الطرفان ───── */
.re-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2em; margin-bottom: .4em; }
.re-party { border: 1px solid #d1d5db; border-top: 3px solid var(--primary-color); border-radius: .3em; padding: .6em .9em; }
.re-party-head { display: flex; align-items: baseline; gap: .5em; margin-bottom: .35em; }
.re-party-head span { font-size: 1.1em; font-weight: 800; color: var(--primary-color); }
.re-party-head small { color: #6b7280; font-size: .8em; }

/* ───── الجداول ───── */
.re-doc .re-table { margin: .3em 0 .6em; font-size: .95em; }
.re-table th { background: var(--secondary-color); color: var(--primary-color); font-weight: 700;
    border-top: 1.5px solid var(--primary-color); border-bottom: 1.5px solid var(--primary-color);
    padding: .45em .5em; text-align: center; white-space: nowrap; }
.re-table td { padding: .4em .5em; border-bottom: 1px solid #e5e7eb; text-align: center; }
.re-table .re-name { text-align: start; }
.re-table .re-seq { width: 2.6em; color: #6b7280; }
.re-num { font-variant-numeric: tabular-nums; white-space: nowrap; direction: ltr; unicode-bidi: isolate; }
.re-table small { font-size: .7em; font-weight: 500; }
.re-table tr { page-break-inside: avoid; }
.re-table thead { display: table-header-group; }
.re-table tfoot td { font-weight: 700; text-align: start; border-bottom: 1px solid #d1d5db; }
.re-table tfoot td.re-num { text-align: center; }
.re-table tfoot tr:first-child td { border-top: 1.5px solid var(--primary-color); }
.re-table tr.re-net-row td { background: var(--secondary-color); color: var(--primary-color); font-weight: 800;
    font-size: 1.06em; border-bottom: 2px solid var(--primary-color); }
.re-values td.re-num { width: 32%; text-align: end; font-weight: 700; }

/* ───── مبلغ الإيصال ───── */
.re-amount { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: .2em 1.4em;
    border: 1.5px solid var(--primary-color); border-radius: .4em; padding: .7em 1.2em; margin: .6em 0 .8em;
    background: var(--secondary-color); page-break-inside: avoid; }
.re-amount-label { font-size: 1.2em; font-weight: 800; color: var(--primary-color); }
.re-amount-value { font-size: 1.9em; font-weight: 800; color: var(--primary-color); text-align: end; }
.re-amount-value small { font-size: .45em; font-weight: 600; }
.re-amount-words { grid-column: 1 / -1; font-size: .92em; color: #374151; border-top: 1px dashed #9ca3af; padding-top: .35em; }
.re-void { position: absolute; top: 9em; inset-inline: 10%; text-align: center; font-size: 6em; font-weight: 800;
    color: rgba(200, 0, 0, .16); transform: rotate(-18deg); pointer-events: none; z-index: 2; }

/* ───── التواقيع والتذييل ───── */
.re-sigs { display: flex; gap: 2em; margin: 1.6em 0 .8em; page-break-inside: avoid; }
.re-sig { flex: 1; }
.re-sig-title { font-weight: 700; color: var(--primary-color); border-bottom: 1px solid #d1d5db; padding-bottom: .2em; margin-bottom: .4em; }
.re-sig-name { font-size: .9em; min-height: 1.5em; }
.re-sig-line { height: 3em; border-bottom: 1px solid #6b7280; position: relative; margin-bottom: .3em; }
.re-sig-line span { position: absolute; bottom: .2em; inset-inline-start: 0; font-size: .75em; color: #9ca3af; }
.re-sig-date { font-size: .78em; color: #6b7280; }
.re-stamp { width: 6.5em; height: 6.5em; border: 1.5px dashed #9ca3af; border-radius: 50%; margin: .4em auto 0;
    display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: .85em; }
.re-foot { display: flex; justify-content: space-between; gap: 1em; border-top: 1px solid var(--primary-color);
    margin-top: 1em; padding-top: .35em; font-size: .75em; color: #6b7280; }
CSS;
    }
}
