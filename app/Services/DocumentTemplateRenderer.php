<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;

class DocumentTemplateRenderer
{
    protected DocumentTemplateVisibility $visibility;

    public function __construct(?DocumentTemplateVisibility $visibility = null)
    {
        $this->visibility = $visibility ?: new DocumentTemplateVisibility;
    }

    /**
     * Render the template with the provided data.
     */
    public function render(DocumentTemplate $template, array $data): string
    {
        $content = $template->content;
        $styles = $template->styles ?? '';

        /* مفاتيح الإجماليات القياسية تُضمَن هنا.

           القوالب صارت تعرض أسطرها بشرط (`@if(totals.shipping > 0)`)،
           ومُطبَّعات بعض الأنواع لا تملأ كل المفاتيح — السندات وقيد اليومية
           وكشف الرواتب لا إجماليات مفصَّلة لها. والشرط على مفتاح غائب يرمي
           `Undefined array key` فيسقط القالب كلّه.

           والقيمة «0.00» لا «0» لتطابق صيغة `number_format` في
           `DocumentTemplateDataService` — فما يُطبع في السطر مطابقٌ لما
           يُطبع في غيره. */
        $data['totals'] = array_merge([
            'subtotal' => '0.00',
            'discount' => '0.00',
            'shipping' => '0.00',
            'tax' => '0.00',
            'grand_total' => '0.00',
            'paid' => '0.00',
            // مرتجعات المستند (فاتورة المبيعات) — `@if(totals.returned > 0)`
            'returned' => '0.00',
            'remaining' => '0.00',
        ], $data['totals'] ?? []);

        /* قوالب نظام التصميم تعلّم نفسها بـ`doc-ds` على غلافها — ومقاساتها
           كلّها بـ`em` منسوبةً إلى حجم الغلاف، فتسري عليها اختيارات
           المستخدم بالتوريث وحده. فرضُ الحجم عليها خليةً خلية بـ`!important`
           يسوّي رأس الجدول بمتنه والتفصيلَ بالعنوان فينهار التدرّج.

           أمّا القوالب القديمة وما يكتبه المستخدم فتُثبّت المقاسات بـ`px`،
           فيبقى لها الفرض وإلّا كان اختيار حجم الخط بلا أثر ظاهر. */
        $ownsTypeScale = str_contains($content, 'doc-ds');

        // 1. Sanitize raw input
        $content = $this->sanitizeHtml($content);
        $styles = $this->sanitizeCss($styles);

        /* ما يكتبه المستخدم يُصرَّف Blade ويُنفَّذ — فيُقصَر قبل تحويل الرموز
           على حقولٍ وشروطٍ بسيطة، وما عداها يُطبع نصّاً. انظر
           [DocumentTemplateGuard]: هو الحارسُ الفعليّ، وما فوقه تنظيفٌ للعرض. */
        $guard = new DocumentTemplateGuard;
        $content = $guard->content($content);
        $styles = $guard->styles($styles);

        // 3. Inject settings and styling
        $settings = $template->settings ?? [];
        $data['settings'] = $settings;

        // العناصر المطفأة تُعلَّم على بنيتها قبل تحويل الرموز، ثم تُحذف من
        // الناتج. الإخفاء بأصناف CSS وحده كان يفشل في أغلب القوالب لأنه
        // يفترض تسمية متفقاً عليها لا تلتزم بها القوالب فعلياً.
        $hidden = $this->visibility->disabledKeys($settings);

        if ($hidden !== []) {
            $content = $this->visibility->annotate($content, $hidden);
        }

        // 2. Parse custom tokens into Blade syntax
        $parsedContent = $this->parseTokens($content);

        // Dynamic settings overrides
        $fontSize = $settings['font_size'] ?? 13;
        $fontFamily = $settings['font_family'] ?? 'Tajawal';
        $direction = $settings['direction'] ?? 'rtl';
        $primaryColor = $settings['primary_color'] ?? '#7367F0';
        $secondaryColor = $settings['secondary_color'] ?? '#82868b';

        // Apply dynamic visibility settings
        $hideCss = '';
        if (isset($settings['show_logo']) && ! $settings['show_logo']) {
            $data['business']['logo'] = null;
            $hideCss .= ".company-logo, .logo-fallback, .modern-logo, .modern-logo-fallback { display: none !important; }\n";
        }
        if (isset($settings['show_qr']) && ! $settings['show_qr']) {
            $data['document']['qr_code'] = null;
            $hideCss .= ".qr-box, .t-qr, .modern-qr, img[src*='qr'] { display: none !important; }\n";
        }
        if (isset($settings['show_barcode']) && ! $settings['show_barcode']) {
            $data['document']['barcode'] = null;
            $hideCss .= ".barcode-box, .t-barcode { display: none !important; }\n";
        }
        if (isset($settings['show_customer']) && ! $settings['show_customer']) {
            // إفراغ المصفوفة كان يمحو مفاتيحها، فيرمي القالب
            // `Undefined array key "name"` وتنهار الصفحة بدل أن يختفي العميل.
            // القيم تُفرَّغ والمفاتيح تبقى.
            $data['customer'] = array_map(fn () => '', $data['customer'] ?? []);
            $hideCss .= ".client-card, .party-card, .emp-card, .wh-info-card:nth-child(2) { display: none !important; }\n";
        }
        if (isset($settings['show_tax']) && ! $settings['show_tax']) {
            $data['totals']['tax'] = null;
            $hideCss .= ".tax-row { display: none !important; }\n";
        }
        if (isset($settings['show_discount']) && ! $settings['show_discount']) {
            $data['totals']['discount'] = null;
            $hideCss .= ".discount-row { display: none !important; }\n";
        }

        /* `@page` تُحقن هنا لا في صفحة الطباعة: المصيّر وحده يعرف
           مقاس ورق القالب، والقاعدة تسري على كل من يعرض الناتج — شاشة
           المعاينة وصفحة الطباعة ومن يدمجه في قالبه.

           وبدونها كان المتصفّح يطبع بهوامشه الافتراضية فوق حشوة الغلاف،
           فتجتمع هامشان ويضيق عرض المطبوعة، ويُطبَع الشريط الحراري على ورقة A4. */
        /* **ولا `auto` مع مقاسٍ في `size`.** كانت القاعدة
           `@page { size: 80mm auto; }` — وهي **غير صالحة**: `size` تقبل
           `auto` وحدها، أو مقاساً واحداً، أو مقاسين، أو اسمَ ورقةٍ مع
           اتجاه. فيرمي المتصفّحُ التصريحَ كلَّه صامتاً ويبقى على ورق
           الطابعة الافتراضي — أي A4. قِسته في المتصفّح:

             size: 80mm auto   ⇒  @page { margin: 3mm; }          ← سقطت
             size: 80mm 297mm  ⇒  @page { size: 80mm 297mm; … }   ← بقيت

           فالشريطُ الحراري كان يُطبع على ورقة A4 لا لخللٍ في التصميم بل
           لحرفٍ واحد. والارتفاع من `document_templates.height` إن ضُبط —
           فطولُ لفّة الطابعة يختلف — وإلّا طولٌ يسع الإيصال بلا قطعه. */
        $thermalHeight = ((float) $template->height) > 0 ? (float) $template->height : 297;

        /* الاتجاه من `document_templates.orientation` — كان عمودُه وحقلُه في
           المحرّر بلا أثر، فيُطبع «كشف مسير رواتب» العريض عمودياً. */
        $landscape = $template->orientation === 'landscape'
            && ! in_array($template->paper_size, ['Thermal_80mm', 'Thermal_58mm'], true);

        $pageRule = match ($template->paper_size) {
            'Thermal_80mm' => '@page { size: 80mm '.$thermalHeight.'mm; margin: 3mm; }',
            'Thermal_58mm' => '@page { size: 58mm '.$thermalHeight.'mm; margin: 2mm; }',
            default => $landscape
                ? '@page { size: A4 landscape; margin: 8mm; }'
                : '@page { size: A4 portrait; margin: 10mm; }',
        };

        $fontSizeRule = $ownsTypeScale ? '' : $this->wrapperSelector([
            'p:not([class*="title"]):not([class*="head"])',
            'td:not([class*="title"])', 'li', 'label', 'small',
        ]).' { font-size: var(--font-size) !important; }';

        // Wrap with template-specific container styles
        $finalHtml = "
            <style>
                {$pageRule}

                :root {
                    --primary-color: {$primaryColor};
                    --secondary-color: {$secondaryColor};
                    --font-size: {$fontSize}px;
                    --font-family: '{$fontFamily}', sans-serif;
                    --direction: {$direction};
                }

                {$styles}
                
                /* Dynamic Settings Hide Rules */
                {$hideCss}

                /* Layout wrapper rules */
                .document-a4 {
                    width: 210mm;
                    min-height: 297mm;
                    padding: 20mm;
                    margin: 0 auto;
                    background: white;
                    box-sizing: border-box;
                    font-family: var(--font-family);
                    font-size: var(--font-size);
                    direction: var(--direction);
                }
                .document-a4.document-landscape {
                    width: 297mm;
                    min-height: 210mm;
                    padding: 12mm;
                }
                .document-thermal-80 {
                    width: 80mm;
                    padding: 4mm;
                    margin: 0 auto;
                    background: white;
                    box-sizing: border-box;
                    font-family: var(--font-family);
                    font-size: var(--font-size);
                    direction: var(--direction);
                }
                .document-thermal-58 {
                    width: 58mm;
                    padding: 2mm;
                    margin: 0 auto;
                    background: white;
                    box-sizing: border-box;
                    font-family: var(--font-family);
                    font-size: var(--font-size);
                    direction: var(--direction);
                }

                /* الخط والحجم: التوريث من الغلاف وحده لا يكفي.
                   قياس القوالب أظهر أن 356 منها تحدّد `font-size` بنفسها
                   و354 تحدّد `color`، وكلها تتغلّب على القيمة الموروثة —
                   فكان اختيار الخط والحجم بلا أثر ظاهر.
                   المُحدِّد يكرّر الصنف ليتفوّق، والعناوين مستثناة من الحجم
                   حتى لا يتساوى العنوان بالنص فينهار التدرّج. */
                {$this->wrapperSelector()} {
                    font-family: var(--font-family) !important;
                    font-size: var(--font-size) !important;
                }
                {$this->wrapperSelector(['p', 'div', 'span', 'td', 'th', 'li', 'label',
            'small', 'strong', 'b', 'em', 'table', 'h1', 'h2', 'h3', 'h4'])} {
                    font-family: var(--font-family) !important;
                }
                {$fontSizeRule}

                /* Dynamic color overrides — applies to common class patterns */
                .header-line,
                .doc-title-badge,
                .card-header-bar,
                .badge-title {
                    background: var(--primary-color) !important;
                    color: white !important;
                }
                .grand-total-row td,
                .tot-row.grand-total,
                .total-row-item.grand,
                .thank-you-msg,
                .modern-doc-number {
                    color: var(--primary-color) !important;
                }
                .doc-title-badge {
                    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
                    border-color: var(--primary-color) !important;
                }
                .logo-fallback,
                .modern-logo-fallback {
                    color: var(--primary-color) !important;
                }
                
                /* Print specific helpers */
                @media print {
                    body {
                        background-color: white !important;
                        padding: 0 !important;
                        margin: 0 !important;
                    }
                    /* الهامش من `@page` وحدها: حشوةٌ هنا تُضاف إليه فيضيق
                       عرض المطبوعة وتنكسر الجداول إلى صفحةٍ ثانية. */
                    .document-a4,
                    .document-thermal-80,
                    .document-thermal-58 {
                        width: auto !important;
                        min-height: 0 !important;
                        padding: 0 !important;
                        margin: 0 !important;
                        box-shadow: none !important;
                        border: none !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            </style>
            <link href=\"https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&family=Tajawal:wght@400;500;700;800&display=swap\" rel=\"stylesheet\">
            
            <div class=\"".$this->getWrapperClass($template->paper_size).($landscape ? ' document-landscape' : '')."\">
                {$parsedContent}
            </div>
        ";

        try {
            $rendered = Blade::render($finalHtml, $data);

            return $hidden === [] ? $rendered : $this->pruneBody($rendered, $hidden);
        } catch (\Throwable $e) {
            // أثر الاستدعاء كان يُطبع في المعاينة فيملأ الورقة بسطور لا تفيد
            // المستخدم. الرسالة تُختصر له، والتفصيل يُسجَّل للمطوّر.
            Log::warning('تعذّر تصيير قالب مستند', [
                'template' => $template->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            $message = e($this->readableError($e->getMessage()));

            return '
                <div dir="rtl" style="font-family:Tajawal,sans-serif;padding:16px;border:1px solid #f5c2c7;'
                ."background:#fdf2f2;border-radius:8px;color:#842029;line-height:1.9\">
                    <strong>تعذّر عرض القالب.</strong>
                    <div style=\"margin-top:6px;font-size:.9em\">{$message}</div>
                    <div style=\"margin-top:6px;font-size:.85em;color:#9c6b6b\">
                        راجع تبويب HTML — غالباً وسم غير مغلق أو رمز حقل غير صحيح.
                    </div>
                </div>
            ";
        }
    }

    /**
     * يترجم رسائل PHP التقنية إلى سبب مفهوم.
     */
    private function readableError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Undefined array key') => 'حقل غير موجود في بيانات هذا النوع من المستندات.',
            str_contains($message, 'Undefined constant') => 'اسم حقل غير معروف — تأكّد من اختياره من لوحة الحقول.',
            str_contains($message, 'unexpected end of file') => 'شرط أو حلقة بلا إغلاق.',
            str_contains($message, 'syntax error') => 'صيغة غير صحيحة في محتوى القالب.',
            default => $message,
        };
    }

    /**
     * مُحدِّد أغلفة الورق، مع لواحق اختيارية.
     *
     * تكرار الصنف (`.document-a4.document-a4`) يرفع الأولوية فوق قواعد
     * الغلاف العادية دون الحاجة إلى `!important` في كل موضع.
     *
     * @param  array<int, string>  $suffixes
     */
    private function wrapperSelector(array $suffixes = []): string
    {
        $wrappers = [
            '.document-a4.document-a4',
            '.document-thermal-80.document-thermal-80',
            '.document-thermal-58.document-thermal-58',
        ];

        if ($suffixes === []) {
            return implode(', ', $wrappers);
        }

        $parts = [];

        foreach ($suffixes as $suffix) {
            foreach ($wrappers as $wrapper) {
                $parts[] = $wrapper.' '.$suffix;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * يحذف العناصر المطفأة من جسم المستند وحده.
     *
     * التحليل يقتصر على ما بعد وسم الغلاف: تمرير كتلة `<style>` على محلّل
     * HTML يعيد كتابتها وقد يفسد قواعدها، ولا حاجة لذلك أصلاً.
     */
    private function pruneBody(string $rendered, array $hidden): string
    {
        $position = strpos($rendered, '<div class="document-');

        if ($position === false) {
            return $rendered;
        }

        return substr($rendered, 0, $position)
            .$this->visibility->prune(substr($rendered, $position), $hidden);
    }

    /**
     * Get CSS wrapper class based on paper size.
     */
    private function getWrapperClass(string $size): string
    {
        switch ($size) {
            case 'Thermal_80mm':
                return 'document-thermal-80';
            case 'Thermal_58mm':
                return 'document-thermal-58';
            case 'A4':
            default:
                return 'document-a4';
        }
    }

    /**
     * Sanitize HTML to prevent server-side code execution or malicious scripts.
     */
    private function sanitizeHtml(string $html): string
    {
        // Remove PHP tags
        $html = preg_replace('/<\?php.*?\?>/s', '', $html);
        $html = preg_replace('/<\?.*?\?>/s', '', $html);
        $html = preg_replace('/\{\{.*?run.*?\}\}/i', '', $html);

        // Remove Script tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // Remove event handlers like onload, onerror
        $html = preg_replace('/on\w+\s*=\s*".*?"/is', '', $html);
        $html = preg_replace('/on\w+\s*=\s*\'.*?\'/is', '', $html);
        // وغيرُ المقتبَس كذلك: `<img src=x onerror=alert(1)>`
        $html = preg_replace('/\son\w+\s*=\s*[^\s>"\']+/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:/i', '$1=$2#', $html);

        return $html;
    }

    /**
     * Sanitize CSS.
     */
    private function sanitizeCss(string $css): string
    {
        // Simple sanitization to ensure no external scripts or expressions are in the CSS
        $css = preg_replace('/behavior\s*:/i', '', $css);
        $css = preg_replace('/-ms-behavior\s*:/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/url\s*\(\s*javascript\s*:/i', '', $css);

        return $css;
    }

    private function parseTokens(string $content): string
    {
        // 1. Loop replacement
        $content = $this->flattenItemLoops($content);
        $content = str_replace('{{#items}}', '@foreach($items as $item)', $content);
        $content = str_replace('{{/items}}', '@endforeach', $content);

        // 2. Loop fields replacement inside loop
        //
        // الحقول الستّة الأخيرة تخصّ كشف الحساب: سطره ليس صنفاً بكمية وسعر
        // بل حركةً بتاريخ ومرجع ومدين ودائن ورصيد جارٍ. حشرُها في حقول
        // الأصناف (السعر = مدين، الضريبة = دائن…) يجعل القالب غير مقروء
        // لمن يحرّره في الشاشة. وهي إضافةٌ آمنة: لا قالب من المئة والثمانين
        // المزروعة يستعمل أياً منها.
        $itemReplacements = [
            '{{code}}' => '{{ $item[\'code\'] ?? \'\' }}',
            '{{name}}' => '{{ $item[\'name\'] ?? \'\' }}',
            '{{quantity}}' => '{{ $item[\'quantity\'] ?? \'\' }}',
            '{{unit}}' => '{{ $item[\'unit\'] ?? \'\' }}',
            '{{price}}' => '{{ $item[\'price\'] ?? \'\' }}',
            '{{discount}}' => '{{ $item[\'discount\'] ?? \'\' }}',
            '{{tax}}' => '{{ $item[\'tax\'] ?? \'\' }}',
            '{{total}}' => '{{ $item[\'total\'] ?? \'\' }}',

            /* تسلسل السطر داخل الحلقة. عمود «#» في القوالب المزروعة
               كان يُملأ بـ`{{code}}`، فيطبع كود الصنف مرّتين: مرّة في
               عمود التسلسل ومرّة في عمود الكود.

               و`??` لازم: متى كُتِب الرمز خارج الحلقة كان `$loop`
               غير معرَّف، وقراءة خاصيّةٍ من غير المعرَّف تحوّلها Laravel
               إلى استثناء فيسقط القالب كلّه. */
            '{{index}}' => '{{ $loop->iteration ?? \'\' }}',

            // كشف الحساب
            '{{date}}' => '{{ $item[\'date\'] ?? \'\' }}',
            '{{reference}}' => '{{ $item[\'reference\'] ?? \'\' }}',
            '{{description}}' => '{{ $item[\'description\'] ?? \'\' }}',
            '{{debit}}' => '{{ $item[\'debit\'] ?? \'\' }}',
            '{{credit}}' => '{{ $item[\'credit\'] ?? \'\' }}',
            '{{balance}}' => '{{ $item[\'balance\'] ?? \'\' }}',
        ];
        $content = str_replace(array_keys($itemReplacements), array_values($itemReplacements), $content);

        // 3. Mapping of dotted variable tokens to PHP array variables
        $variables = [
            'business.name' => '$business[\'name\']',
            'business.logo' => '$business[\'logo\']',
            'business.phone' => '$business[\'phone\']',
            'business.email' => '$business[\'email\']',
            'business.tax_number' => '$business[\'tax_number\']',
            'business.commercial_registration' => '$business[\'commercial_registration\']',
            'business.address' => '$business[\'address\']',
            'business.website' => '$business[\'website\']',
            'business.currency' => '$business[\'currency\']',

            'customer.name' => '$customer[\'name\']',
            'customer.tax_number' => '$customer[\'tax_number\']',
            'customer.phone' => '$customer[\'phone\']',
            'customer.address' => '$customer[\'address\']',
            'customer.code' => '$customer[\'code\']',

            /* «التصميم الموحّد» — يملؤها `DocumentTemplateDataService::withContext()`.
               و`title_en` **قبل** `title`: الاستبدالُ العامّ بالنصّ بعدها
               يحوِّل `document.title` داخل `document.title_en` فيفسده. */
            'document.title_en' => '$document[\'title_en\'] ?? \'\'',
            'document.time' => '$document[\'time\'] ?? \'\'',
            'document.employee' => '$document[\'employee\'] ?? \'\'',
            'document.branch' => '$document[\'branch\'] ?? \'\'',

            'document.title' => '$document[\'title\']',
            'document.number' => '$document[\'number\']',
            'document.date' => '$document[\'date\']',
            'document.due_date' => '$document[\'due_date\']',
            'document.payment_method' => '$document[\'payment_method\']',
            'document.notes' => '$document[\'notes\']',
            'document.qr_code' => '$document[\'qr_code\']',
            'document.barcode' => '$document[\'barcode\']',

            'totals.subtotal' => '$totals[\'subtotal\']',
            'totals.discount' => '$totals[\'discount\']',
            // الشحن لم يكن له رمز، فالقالب الذي يعرضه كان يطبع نصّه حرفياً
            'totals.shipping' => '$totals[\'shipping\']',
            'totals.tax' => '$totals[\'tax\']',
            'totals.grand_total' => '$totals[\'grand_total\']',
            'totals.paid' => '$totals[\'paid\']',
            'totals.returned' => '$totals[\'returned\']',
            'totals.remaining' => '$totals[\'remaining\']',

            // كشف الحساب — الرصيد الافتتاحي وحركة الفترة والرصيد الختامي.
            //
            // `??` هنا لأن كل قالبٍ آخر لا يمرّر `statement`، فلو استُعملت
            // هذه الرموز في قالب فاتورة لسقط بـ`Undefined variable`.
            //
            // **ولا يجوز أن يبدأ التعبير بقوس.** `neutralizeUnknownTokens()`
            // لا تثق إلا بما يبدأ بـ`$`، فتعامل ما عداه نصّاً وتُهرّبه
            // بـ`@{{ … }}` — فكانت هذه الرموز تُطبَع حرفياً في المطبوعة
            // (`{{('' ?? statement['from']$)}}`) بدل قيمها.
            'statement.from' => '$statement[\'from\'] ?? \'\'',
            'statement.to' => '$statement[\'to\'] ?? \'\'',
            'statement.opening' => '$statement[\'opening\'] ?? \'\'',
            'statement.debit' => '$statement[\'debit\'] ?? \'\'',
            'statement.credit' => '$statement[\'credit\'] ?? \'\'',
            'statement.closing' => '$statement[\'closing\'] ?? \'\'',
            'statement.direction' => '$statement[\'direction\'] ?? \'\'',
            'statement.count' => '$statement[\'count\'] ?? \'\'',
        ];

        // Replace both $var and var format inside double curly braces first, and then generally
        // This ensures correct replacement inside @if conditions and standard echo tags
        foreach ($variables as $old => $new) {
            // First clean double curly braces: e.g. {{business.name}} -> {{$business['name']}}
            $content = str_replace('{{'.$old.'}}', '{{'.$new.'}}', $content);
            $content = str_replace('{{ '.$old.' }}', '{{ '.$new.' }}', $content);
            $content = str_replace('{{$'.$old.'}}', '{{'.$new.'}}', $content);
            $content = str_replace('{{ $'.$old.' }}', '{{ '.$new.' }}', $content);

            // Also clean raw tags: e.g. {!! document.qr_code !!} -> {!! $document['qr_code'] !!}
            $content = str_replace('{!!'.$old.'!!}', '{!!'.$new.'!!}', $content);
            $content = str_replace('{!! '.$old.' !!}', '{!! '.$new.' !!}', $content);
            $content = str_replace('{!!$'.$old.'!!}', '{!!'.$new.'!!}', $content);
            $content = str_replace('{!! $'.$old.' !!}', '{!! '.$new.' !!}', $content);

            // Then clean general text/expressions like @if(business.logo) or @if($totals.discount > 0)
            $content = str_replace('$'.$old, $new, $content);
            $content = str_replace($old, $new, $content);
        }

        /* مفاتيحُ المستندات المسمّاة — `contract.*` (عقد العمل)، `advance.*`
           (سند السلفة)، `commission.*` (سند صرف العمولة). يملؤها المُطبِّع
           مصفوفةً باسم مجالها، فلا تُسرد مفاتيحها واحداً واحداً هنا.

           `?? ''` لازم: القالبُ يُعايَن ببياناتٍ تجريبية قد لا تحمل المجال،
           وقراءةُ متغيّرٍ غير معرَّف تُسقط القالب كلّه. والتعبيرُ يبدأ بـ`$`
           كي لا تُهرّبه `neutralizeUnknownTokens()`. والشرطُ السابق يمنع
           مطابقةَ ما بعد حرفٍ أو شرطة. ولا يُمَسّ إلّا ما داخل `{{ }}`
           و`{!! !!}` وشروط `@if/@elseif/@unless` — نصٌّ عاديّ في القالب
           («contract.pdf») يبقى نصّاً. */
        $content = (string) preg_replace_callback(
            '/\{\{.*?\}\}|\{!!.*?!!\}|@(?:if|elseif|unless)\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/s',
            fn (array $block) => (string) preg_replace_callback(
                /* `slip.*` (قسيمة الراتب) و`payroll.*` (كشف المسير) و`item.*`
                   (حقلٌ مسمّى في سطر البنود، مثل `{{item.basic}}` داخل
                   `{{#items}}`) — أُضيفت لـ«تصميم الموارد البشرية».
                   و`payment.*` لإيصال دفعة الإيجار العقاري (`realestate_receipt`). */
                '/(?<![\w\-\'\[])\$?\b(contract|advance|commission|slip|payroll|payment|item)\.([a-z_]+)\b/',
                fn (array $m) => '$'.$m[1].'[\''.$m[2].'\'] ?? \'\'',
                $block[0]
            ),
            $content
        );

        return $this->neutralizeUnknownTokens($content);
    }

    /**
     * يترك حلقة الأصناف الخارجية وحدها ويحذف ما تداخل داخلها.
     *
     * `items` قائمة مسطّحة، فحلقة داخل حلقة عليها لا معنى لها — لكنها تظهر
     * حين يفسد محتوى القالب (نسخ كتلة داخل نفسها، أو تحويل مكسور). وهي
     * ليست خطأً عادياً: كل مستوى تداخل يضرب عدد الصفوف في عدد الأصناف،
     * فيستنفد المستوى الثالث ذاكرة PHP كاملةً. ونفاد الذاكرة خطأ قاتل لا
     * يلتقطه `try/catch` في `render`، فتسقط الصفحة كلّها بخمسمئة بدل أن
     * تظهر رسالة مفهومة. التسطيح هنا يسبق التصريف فلا يصل التداخل إليه.
     *
     * الإغلاق الزائد بلا فتح يُحذف كذلك: `@endforeach` وحده خطأ تصريف.
     */
    private function flattenItemLoops(string $content): string
    {
        $depth = 0;

        return (string) preg_replace_callback(
            '/\{\{#items\}\}|\{\{\/items\}\}/',
            function (array $match) use (&$depth) {
                if ($match[0] === '{{#items}}') {
                    $depth++;

                    return $depth === 1 ? $match[0] : '';
                }

                if ($depth === 0) {
                    return '';
                }

                $depth--;

                return $depth === 0 ? $match[0] : '';
            },
            $content
        );
    }

    /**
     * يحوّل ما تبقّى من `{{ … }}` غير المعروف إلى نص يُطبع كما هو.
     *
     * أي قوس مزدوج يكتبه المستخدم في المحرّر — أو رمز حقل أخطأ في اسمه —
     * كان يصل إلى Blade فيُصرَّف كتعبير PHP، ويسقط القالب كاملاً برسالة
     * `syntax error` بدل أن يظهر النص كما هو. هنا يُعطَّل التصريف عنه.
     */
    private function neutralizeUnknownTokens(string $content): string
    {
        // التعبيرات الصحيحة الناتجة عن الاستبدال تبدأ بـ `$` — وما عداها نص
        $content = preg_replace_callback(
            '/\{\{(?!\s*\$)(.*?)\}\}/s',
            fn (array $match) => '@{{'.$match[1].'}}',
            $content
        );

        return (string) preg_replace_callback(
            '/\{!!(?!\s*\$)(.*?)!!\}/s',
            fn (array $match) => '@{!!'.$match[1].'!!}',
            $content
        );
    }
}
