<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

/**
 * قالب «الإيصال الحراري 80 مم» المبني على مطبوعة النظام.
 *
 * كان هذا التصميم يعيش في `Modules/Sales/resources/views/invoices/pdf.blade.php`
 * وحده — قالبٌ مدمجٌ في الكود لا يظهر في «تصاميم مستندات المبيعات» ولا
 * يُختار من شاشة الطباعة ولا يُعدَّل من الواجهة. نُقل إلى `document_templates`
 * فصار كبقيّة القوالب: يُعرَض ويُختار ويُنسَخ ويُحرَّر.
 *
 * **التحويل:** متغيّرات Blade (`$invoice->…`, `OrganizationInfo::…`) بُدّلت
 * برموز المحرّك (`{{document.number}}`, `{{business.name}}`, وحلقة
 * `{{#items}}`) — فالقالب يُصيَّر الآن عبر `DocumentTemplateRenderer` لا عبر
 * Blade مباشرةً.
 *
 * **إعادة تصميم 2026-09-03:** نفس هيكل `print.blade.php` (ترويسة المنشأة،
 * بيانات العميل، جدول الأصناف، الإجماليات، QR، التوقيع) لكن بإصلاح عيوبه:
 *
 * - رمز QR كان يُطبع بـ`{!!document.qr_code!!}` بلا وسم `<img>` — والقيمة
 *   رابط صورة (`data:image/svg+xml;base64,…`) لا HTML، فيظهر نصّاً عشوائياً
 *   على الورق بدل الرمز. صار داخل `<img src="{{document.qr_code}}">`
 *   كبقيّة القوالب.
 * - أُضيف تسلسل الصنف وعمود الخصم لكل بند (كانا في `print.blade.php` ولم
 *   ينتقلا)، وطريقة الدفع، والرقم الضريبي للمنشأة (لا العميل وحده) — إلزاميٌّ
 *   على أي إيصال ضريبي.
 * - سطر «المبلغ المستحق» يظهر حين يكون أكبر من صفر، أو حين يُفسَّر بسطرٍ
 *   قبله (مدفوع أو مرتجع) — فالفاتورة المسدَّدة تُطبع «المستحق: 0.00» بعد
 *   سطرَي المدفوع والمرتجع لا بإجماليٍّ معلَّق.
 * - **2026-09-11:** أُضيف سطر «مرتجع» (`totals.returned`). كانت فاتورةٌ سوّاها
 *   إشعارٌ دائن أو رُدّ بعضُها تُطبع بإجمالي ٢٤٨٫٤٠ ومستحقٍّ ١٣٤٫٤٠ ولا سطرَ
 *   يقول أين ذهب الفرق. و«مدفوع مقدماً» صار «المدفوع»: القيمةُ مجموعُ كلّ
 *   التحصيلات لا الدفعة المقدَّمة وحدها.
 * - حُذف الاعتماد على شعار Google Fonts هنا: المصيِّر يحقنه مرّةً واحدة
 *   لكل القوالب ويفرض الخط بـ`!important`، فتكراره كان طلب شبكةٍ زائداً
 *   بلا أثر.
 * - كان تعليق Blade داخل `content()` (`{{-- … --}}` قبل شرط الخصم) هو
 *   مصدر "@" الظاهرة وحدها بين سطرَي الإجمالي والضريبة في المطبوعة:
 *   `DocumentTemplateRenderer::neutralizeUnknownTokens()` يسبق أيَّ
 *   `{{…}}` لا يبدأ بـ`$` بـ`@` قبل أن يصل النصّ إلى Blade، فيزيل مصرِّفُ
 *   Blade جسمَ التعليق `{{-- … --}}` ويُبقي الـ`@` يتيماً. **لا تُكتب
 *   تعليقات Blade ولا نصّ `@if(...)` داخل `content()`/`styles()` هنا —
 *   لا كتعليق ولا حتى داخل تعليق HTML عادي، لأن Blade يصرِّف الأسطر كلَّها
 *   حرفياً بلا وعيٍ بوسوم HTML من حولها.**
 */
class SalesInvoiceThermalTemplateSeeder extends Seeder
{
    public const NAME = 'فاتورة مبيعات - إيصال حراري 80 مم (مطبوعة النظام)';

    public function run(): void
    {
        $template = DocumentTemplate::updateOrCreate(
            ['name' => self::NAME, 'document_type' => 'sales_invoice'],
            [
                'template_type' => 'ready',
                'paper_size' => 'Thermal_80mm',
                'width' => 80,
                'height' => null,
                'orientation' => 'portrait',
                'is_active' => true,
                'is_default' => true,
                'content' => $this->content(),
                'styles' => $this->styles(),
                // مصفوفةً لا نصّاً: العمود مُحوَّل `array` في الموديل،
                // فترميزُه هنا يُرمّز مرّتين ويعود نصّاً عند القراءة
                'settings' => [
                    'show_logo' => false,
                    'show_qr' => true,
                    'show_barcode' => false,
                    'show_customer' => true,
                    'show_tax' => true,
                    'show_discount' => true,
                    'font_size' => 12,
                    'direction' => 'rtl',
                ],
                'user_id' => null,
                'branch_id' => null,
            ]
        );

        /* افتراضيٌّ واحد لكل نوع: لو بقي القديم افتراضياً معه لاختار
           `defaultTemplateFor()` أوّلَ ما يعود به الاستعلام — فيتبدّل
           القالب المطبوع بلا سببٍ ظاهر. */
        DocumentTemplate::where('document_type', 'sales_invoice')
            ->whereKeyNot($template->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $this->command?->info('قالب الإيصال الحراري: صار القالب الافتراضي لفواتير المبيعات.');
    }

    private function styles(): string
    {
        /* لا `@import` لخطوطٍ خارجية: `DocumentTemplateRenderer::render()`
           يحقن رابط Google Fonts نفسه مرّةً واحدة لكل القوالب، ويفرض
           `--font-family` بـ`!important` على كل الوسوم الشائعة — فتكرارُه هنا
           تعليقٌ إضافيٌّ يبطّئ أول طباعة على شبكةٍ ضعيفة بلا فائدة. */
        return <<<'STYLES_SQL'
.fw-receipt {
    text-align: center;
    color: #000;
    font-weight: bold;
}

.fw-receipt .fw-logo {
    max-height: 46px;
    margin-bottom: 6px;
}

.fw-receipt .fw-title {
    font-size: 1.4em;
    margin: 0 0 4px;
}

.fw-receipt .fw-header,
.fw-receipt .fw-party,
.fw-receipt .fw-meta {
    padding-bottom: 8px;
    margin-bottom: 8px;
    border-bottom: 1px dashed #999;
}

.fw-receipt p {
    margin: 2px 0;
}

.fw-receipt .fw-party,
.fw-receipt .fw-meta {
    text-align: right;
}

.fw-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin: 2px 0;
}

.fw-items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    font-size: 0.9em;
}

.fw-items th,
.fw-items td {
    padding: 3px 2px;
    border-bottom: 1px dashed #ccc;
    text-align: center;
    word-break: break-word;
}

.fw-items th {
    border-bottom: 1px solid #000;
    /* على ٨٠ مم تنكسر «الخصم» إلى «الخص/م» حين يضيق عمودُها — والعنوانُ
       المقطوع يُقرأ خطأً. النصُّ لا يلتفّ، والعرضُ وحده هو ما يتغيّر. */
    white-space: nowrap;
    font-size: 0.88em;
}

.fw-items td.fw-item-name {
    text-align: right;
}
/* الوحدة تحت الكمية: عرضُ الورقة لا يحتمل عموداً سابعاً، وسطرٌ بلا
   وحدةٍ محفوظة يُخرج الوسمَ فارغاً فلا يظهر منه شيء. */
.fw-unit { display: block; font-size: .8em; font-weight: 600; opacity: .75; line-height: 1.4; }

.fw-totals {
    padding-top: 6px;
    margin-bottom: 10px;
    border-top: 1px dashed #999;
}

.fw-totals .fw-grand {
    font-size: 1.15em;
    border-top: 1px solid #000;
    padding-top: 4px;
    margin-top: 4px;
}

.fw-notes {
    text-align: right;
    padding: 6px 0;
    border-top: 1px dashed #999;
    font-weight: normal;
}

.fw-qr {
    margin: 10px 0;
}

.fw-qr img {
    width: 110px;
    height: 110px;
}

.fw-signature {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px dashed #999;
}

.fw-signature p {
    margin: 10px 0 2px;
}

.fw-thankyou {
    margin-top: 8px;
    font-style: italic;
}

@media print {
    .fw-qr img {
        width: 90px;
        height: 90px;
    }
}
STYLES_SQL;
    }

    private function content(): string
    {
        return <<<'CONTENT_SQL'
<div class="fw-receipt">
    <div class="fw-header">
        @if(!empty(business.logo))
        <img src="{{business.logo}}" class="fw-logo" alt="">
        @endif
        <h1 class="fw-title">{{document.title}}</h1>
        <p>{{business.name}}</p>
        <p>{{business.address}}</p>
        @if(!empty(business.phone))
        <p>هاتف: {{business.phone}}</p>
        @endif
        @if(!empty(business.tax_number))
        <p>الرقم الضريبي: {{business.tax_number}}</p>
        @endif
    </div>

    <div class="fw-party">
        <p><strong>فاتورة الى:</strong> {{customer.name}}</p>
        @if(!empty(customer.address))
        <p>{{customer.address}}</p>
        @endif
        @if(!empty(customer.code))
        <p>كود العميل: {{customer.code}}</p>
        @endif
        @if(!empty(customer.tax_number))
        <p>الرقم الضريبي: {{customer.tax_number}}</p>
        @endif
        @if(!empty(customer.phone))
        <p>جوال العميل: {{customer.phone}}</p>
        @endif
    </div>

    <div class="fw-meta">
        <div class="fw-row"><span>رقم الفاتورة</span><span>{{document.number}}</span></div>
        <div class="fw-row"><span>التاريخ</span><span>{{document.date}}</span></div>
        @if(!empty(document.payment_method))
        <div class="fw-row"><span>طريقة الدفع</span><span>{{document.payment_method}}</span></div>
        @endif
    </div>

    <table class="fw-items">
        <thead>
            <tr>
                <th width="6%">#</th>
                <th width="30%">الصنف</th>
                <th width="16%">الكمية</th>
                <th width="16%">السعر</th>
                <th width="14%">الخصم</th>
                <th width="18%">المجموع</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td>{{index}}</td>
                <td class="fw-item-name">{{name}}</td>
                <td>{{quantity}}<span class="fw-unit">{{unit}}</span></td>
                <td>{{price}}</td>
                <td>{{discount}}</td>
                <td>{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="fw-totals">
        <div class="fw-row"><span>المجموع</span><span>{{totals.subtotal}} ر.س</span></div>

        @if(totals.discount > 0)
        <div class="fw-row"><span>الخصم</span><span>-{{totals.discount}} ر.س</span></div>
        @endif

        @if(totals.shipping > 0)
        <div class="fw-row"><span>الشحن</span><span>{{totals.shipping}} ر.س</span></div>
        @endif

        @if(totals.tax > 0)
        <div class="fw-row tax-row"><span>الضريبة</span><span>{{totals.tax}} ر.س</span></div>
        @endif

        <div class="fw-row fw-grand"><span>الإجمالي</span><span>{{totals.grand_total}} ر.س</span></div>

        @if(totals.paid > 0)
        <div class="fw-row"><span>المدفوع</span><span>{{totals.paid}} ر.س</span></div>
        @endif

        @if(totals.returned > 0)
        <div class="fw-row"><span>مرتجع</span><span>- {{totals.returned}} ر.س</span></div>
        @endif

        @if(totals.paid > 0 || totals.returned > 0 || totals.remaining > 0)
        <div class="fw-row"><span>المبلغ المستحق</span><span>{{totals.remaining}} ر.س</span></div>
        @endif
    </div>

    @if(!empty(document.notes))
    <div class="fw-notes">{{document.notes}}</div>
    @endif

    @if(!empty(document.qr_code))
    <div class="fw-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif

    <div class="fw-signature">
        <p>الاسم: ________________</p>
        <p>التوقيع: _______________</p>
    </div>

    <p class="fw-thankyou">شكراً لتعاملكم معنا</p>
</div>
CONTENT_SQL;
    }
}
