<?php

namespace App\Services\Sales;

use App\Enums\EmailStatus;
use App\Enums\RecipientType;
use App\Models\DocumentTemplate;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\BusinessInfo;
use App\Support\LanguageSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use TCPDF;

/**
 * إرسالُ مستندِ مبيعاتٍ إلى بريد العميل — بقالبه المصمَّم، لا برابط.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * زرٌّ اسمُه «إرسال بالبريد» في شاشة عرض السعر والمرتجع والإشعار، وتحته
 * `href="https://wa.me/…"` — أي أنّه **يفتح واتساب**. والدفعة بلا زرٍّ
 * أصلاً. وما كان يرسل فعلاً كان يرسل غيرَ ما يَعِد:
 *
 *   • `QuoteController::sendQuoteLink()` يرسل **رابطاً** بقالب بريدٍ
 *     مثبَّتٍ في `resources/views/emails`، فمن لا حساب له لا يفتحه.
 *   • `InvoicesController::send_invoice()` يرفق PDF مبنيّاً من قالب
 *     بلايد المدمج `sales::invoices.print` — لا من القالب الذي صمّمه
 *     المستخدم في «قوالب المستندات» وعيّنه افتراضياً لنوعه. فالمطبوعُ
 *     من الشاشة شيءٌ والمرسَلُ بالبريد شيءٌ آخر.
 *   • ولا واحدٌ منها يكتب في `email_logs`، فلا أثر لرسالةٍ لم تصل.
 *
 * ═════════════════════ وما صار ═════════════════════
 *
 * مسارٌ واحد لكلّ الأنواع الستّة (عرض سعر، أمر بيع، فاتورة، مرتجع، إشعار
 * دائن، سند قبض):
 *
 *   ١. **المستند** يُصيَّر من `document_templates` — القالبَ الذي اختاره
 *      المرسِل من القائمة، وإلّا افتراضيَّ نوعه. وهو نفسُه ما تعرضه شاشة
 *      الطباعة، فالمرسَلُ والمطبوعُ واحد. وإن لم يكن للنوع قالبٌ فعّال
 *      فالمدمجُ الذي يمرّره النداء (`$fallbackHtml`) — احتياطٌ لا يُنزَع.
 *   ٢. **نصُّ الرسالة** من `email_templates` برمزٍ من `TEMPLATE_CODES`،
 *      يحرّره المستخدم من شاشة «قوالب البريد».
 *   ٣. المستند يُرفَق PDF، ويُكتب سطرٌ في `email_logs` نجح الإرسال أم فشل.
 *
 * والفشلُ **يعود إلى النداء** ولا يُبتلع: `EmailTemplate::sendTemplatedEmail()`
 * تعيد `void` وتكتب السطرَ الفاشل وتمضي — فالمستخدم يقرأ «تم الإرسال»
 * ولم يُرسَل شيء. هنا نتيجةٌ صريحة يبني عليها المتحكّم رسالته.
 */
class SalesDocumentMailer
{
    /**
     * رمزُ قالب البريد لكل نوع مستند — يزرعها `SalesEmailTemplateSeeder`.
     *
     * المفاتيح هي `document_type` نفسُه في `document_templates`، فلا
     * يحتاج النداءُ إلى ترجمةٍ بينهما.
     */
    public const TEMPLATE_CODES = [
        'quote' => 'sales.quote',
        'sales_order' => 'sales.sales_order',
        'sales_invoice' => 'sales.sales_invoice',
        'tax_invoice' => 'sales.sales_invoice',
        'simplified_invoice' => 'sales.sales_invoice',
        'sales_return' => 'sales.sales_return',
        'credit_note' => 'sales.credit_note',
        'receipt_voucher' => 'sales.receipt_voucher',
        // تأكيدُ الحجز — قالبُه من `ReservationEmailTemplateSeeder` في وحدة الحجوزات
        'reservation' => 'reservations.reservation',
        // موعدُ عميلٍ مُشارَك — بلا مستندٍ ولا مرفق؛ `AppointmentClientShare`
        'appointment' => 'reservations.appointment',
    ];

    /**
     * يرسل المستند.
     *
     * @param  string  $documentType  نوعُه في «قوالب المستندات» (`quote`, `sales_invoice`, …)
     * @param  int|string  $documentId  معرّفُ المستند
     * @param  string|null  $recipient  بريدُ المستلِم
     * @param  array<string, string>  $variables  متغيّراتُ قالب البريد (`client`, `number`, `total`, `link`, …)
     * @param  int|string|null  $documentTemplateId  قالبُ الطباعة المختار — أو الافتراضيّ
     * @param  string|null  $fallbackHtml  قالبُ الوحدة المدمج، حين لا قالبَ مصمَّم للنوع
     * @param  int|null  $clientId  لسجلّ البريد
     * @param  string|null  $dataType  مصدرُ بيانات المستند حين يخالف نوعَ القالب
     *                                 (سندُ القبض: قوالبُ `receipt_voucher`
     *                                 وبياناتٌ من `client_payment`)
     * @return array{ok: bool, error: string|null, email: string|null}
     */
    public function send(
        string $documentType,
        $documentId,
        ?string $recipient,
        array $variables = [],
        $documentTemplateId = null,
        ?string $fallbackHtml = null,
        ?int $clientId = null,
        ?string $dataType = null
    ): array {
        $recipient = trim((string) $recipient);

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->failure(__('sales_documents.email.no_recipient'), null);
        }

        $variables += [
            'business' => BusinessInfo::name(),
            'client' => '',
            'number' => (string) $documentId,
            'date' => LanguageSettings::formatDate(now()),
            'total' => '',
            'link' => '',
            'note' => '',
            'attachment_note' => '',
        ];

        /* القالبُ المختار، وإلّا افتراضيُّ النوع. ويُحتفَظ به لأن مقاسَ
           الورق منه: المرفقُ يخرج على مقاس ما صُمّم عليه لا على A4 دائماً. */
        $template = $this->resolveTemplate($documentType, $documentTemplateId);
        $documentHtml = $template ? $this->documentHtml($dataType ?? $documentType, $documentId, $template) : null;

        /* سقط تصييرُ القالب ← المدمجُ يُطبع على A4: هو صفحةُ بلايد مصمَّمةٌ
           لها، وحشرُها في شريطٍ حراريٍّ ٨٠ مم يقطع أعمدتَها. */
        if ($documentHtml === null) {
            $documentHtml = $fallbackHtml;
            $template = null;
        }

        $attachment = $documentHtml ? $this->pdf($documentHtml, $template) : null;

        /* السطرُ الذي يعِد بمرفقٍ لا يُكتب إلّا إن كان المرفقُ فعلاً — لا
           قالبَ مصمَّمٌ ولا مدمجٌ يُصيَّر يعني رسالةً بلا PDF، وقولُها
           «نسخةٌ مرفقة» حينها كذبةٌ يقرؤها العميل. */
        $variables['attachment_note'] = $attachment !== null
            ? __('sales_documents.email.attachment_note')
            : '';

        $message = $this->messageBody($documentType, $variables);

        if ($message === null) {
            /*
                الرسالةُ تسمّي زرَّ البذر الصحيح في «تحديث النظام»: قوالبُ
                `reservations.*` تُزرع من «قالبا بريد الحجز والموعد» لا من
                «قوالب بريد مستندات المبيعات» — الرسالةُ الموحّدة كانت تدلّ
                مرسِلَ الحجز على زرٍّ لا يزرع قالبَه فيضغطه ويفشل ثانيةً.
            */
            $code = self::TEMPLATE_CODES[$documentType] ?? $documentType;
            $key = str_starts_with($code, 'reservations.')
                ? 'sales_documents.email.no_template_reservations'
                : 'sales_documents.email.no_template';

            return $this->failure(__($key, ['code' => $code]), $recipient);
        }

        $fileName = $this->fileName($documentType, $variables['number']);

        try {
            Mail::html($message['html'], function ($mail) use ($recipient, $message, $attachment, $fileName) {
                $mail->to($recipient)->subject($message['subject']);

                if ($attachment !== null) {
                    $mail->attachData($attachment, $fileName, ['mime' => 'application/pdf']);
                }
            });
        } catch (\Throwable $e) {
            Log::error('فشل إرسال مستند مبيعات بالبريد', [
                'document_type' => $documentType,
                'document_id' => $documentId,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            $this->log($message, $recipient, $clientId, EmailStatus::Failed, $e->getMessage());

            return $this->failure($e->getMessage(), $recipient);
        }

        $this->log($message, $recipient, $clientId, EmailStatus::Sent, null);

        return ['ok' => true, 'error' => null, 'email' => $recipient];
    }

    /** القالبُ المطلوب باسمه إن مُرّر ووافق النوع، وإلّا افتراضيُّ النوع */
    protected function resolveTemplate(string $documentType, $templateId): ?DocumentTemplate
    {
        if (is_numeric($templateId)) {
            $chosen = DocumentTemplate::where('id', (int) $templateId)
                ->where('document_type', $documentType)
                ->first();

            if ($chosen) {
                return $chosen;
            }
        }

        return \App\Support\PrintSettings::defaultTemplateFor($documentType);
    }

    /**
     * المستند مصيَّراً من قالبه المصمَّم — أو `null` فيتولّاه المدمج.
     *
     * تعذُّرُ التصيير لا يمنع الإرسال: الرسالةُ تخرج بلا مرفق، ويبقى أثرُ
     * السبب في سجلّ الأخطاء. مستندٌ يصل بلا صورةٍ خيرٌ من مستندٍ لا يصل.
     */
    protected function documentHtml(string $documentType, $documentId, DocumentTemplate $template): ?string
    {
        try {
            $data = app(DocumentTemplateDataService::class)->getDocumentData($documentType, $documentId);

            return app(DocumentTemplateRenderer::class)->render($template, $data);
        } catch (\Throwable $e) {
            Log::warning('تعذّر تصيير قالب المستند للبريد', [
                'document_type' => $documentType,
                'document_id' => $documentId,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * نصُّ الرسالة من قالب البريد، متغيّراتُه مستبدَلة.
     *
     * `null` حين لا قالبَ فعّال — ولا تُخترَع رسالةٌ بديلة: قالبُ البريد
     * هو ما يحرّره المستخدم، وإرسالُ نصٍّ لم يكتبه أحدٌ باسم منشأته أسوأ
     * من قولِ «القالب غير مفعّل».
     *
     * @param  array<string, string>  $variables
     * @return array{subject: string, html: string, template_id: int|null}|null
     */
    protected function messageBody(string $documentType, array $variables): ?array
    {
        $code = self::TEMPLATE_CODES[$documentType] ?? null;

        if ($code === null) {
            return null;
        }

        $template = EmailTemplate::query()->byCode($code)->active()->first();

        if (! $template) {
            return null;
        }

        $replaced = $template->replaceVariables($variables);

        return [
            'subject' => $replaced['subject'],
            'html' => $replaced['html'] ?: nl2br(e($replaced['text'] ?? '')),
            'template_id' => $template->template_id,
        ];
    }

    /**
     * المستند PDF — أو `null` إن سقط التوليد، فتخرج الرسالة بلا مرفق.
     *
     * **على مقاس ورق القالب لا على A4 دائماً.** كان المولّد يُنشأ بـ
     * `PDF_PAGE_FORMAT` مهما كان القالب، فإيصالٌ حراريٌّ صُمّم على ٨٠ مم
     * يُفرَد على عرض A4: الخطُّ تائهٌ في بياضٍ عريض، والسطورُ التي صُمّمت
     * لشريطٍ ضيّق تتباعد حتى لا تُقرأ. والمقاسُ في `document_templates`
     * نفسِه، وهو ما تبني عليه `DocumentTemplateRenderer` قاعدةَ `@page`.
     */
    protected function pdf(string $html, ?DocumentTemplate $template = null): ?string
    {
        try {
            [$format, $orientation, $margin] = $this->pageGeometry($template);

            $pdf = new TCPDF($orientation, 'mm', $format, true, 'UTF-8', false);
            $pdf->SetCreator(config('app.name'));
            $pdf->SetAuthor(config('app.name'));
            $pdf->SetMargins($margin, $margin, $margin);
            $pdf->SetAutoPageBreak(true, $margin);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->setRTL(LanguageSettings::isRtl());
            $pdf->SetFont('aealarabiya', '', $this->baseFontSize($template));
            $ready = $this->flexRowsToTables(
                $this->localizeImages($this->resolveCssVariables($html))
            );

            $pdf->writeHTML($ready, true, false, true, false, '');

            return $pdf->Output('', 'S');
        } catch (\Throwable $e) {
            Log::warning('تعذّر توليد PDF لمرفق البريد', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * مقاسُ الصفحة واتجاهُها وهامشُها من القالب.
     *
     * الأرقامُ هي نفسُها في `DocumentTemplateRenderer::render()` — ثلاثةُ
     * مليمترات للثمانين وملّيمتران للثمانية والخمسين — فالمرفقُ يخرج على
     * ما يُعاين على الشاشة. والارتفاع من عمود `height` إن ضُبط، فطولُ لفّة
     * الطابعة يختلف.
     *
     * @return array{0: string|array{0: float, 1: float}, 1: string, 2: float}
     */
    protected function pageGeometry(?DocumentTemplate $template): array
    {
        /* بلا قالبٍ مصمَّم فالمدمجُ، وهو مكتوبٌ لـA4. والفحصُ على `null`
           لازمٌ لا تجميليّ: قراءةُ `paper_size` من `null` ترمي، والالتقاطُ
           في `pdf()` يبتلعها فتخرج الرسالةُ **بلا مرفق** بلا سببٍ ظاهر. */
        if ($template === null) {
            return ['A4', 'P', 10.0];
        }

        $height = (float) $template->height > 0 ? (float) $template->height : 297.0;

        return match ($template->paper_size) {
            'Thermal_80mm' => [[80.0, $height], 'P', 3.0],
            'Thermal_58mm' => [[58.0, $height], 'P', 2.0],
            default => [
                $template->paper_size ?: 'A4',
                ($template->orientation ?? 'portrait') === 'landscape' ? 'L' : 'P',
                10.0,
            ],
        };
    }

    /** حجمُ الخطّ الأساس من إعدادات القالب — هو ما يُبنى عليه كلُّ `em` فيه */
    protected function baseFontSize(?DocumentTemplate $template): float
    {
        $size = (float) (($template?->settings['font_size'] ?? null) ?: 0);

        return $size > 0 ? $size : 11.0;
    }

    /**
     * يحوّل صفوف `display: flex` إلى جداولَ من خليّتين.
     *
     * **وإلّا التصقت التسمية بقيمتها.** القوالب تكتب سطر «تسمية ← قيمة»
     * هكذا:
     *
     *     <div class="fw-row"><span>رقم الفاتورة</span><span>005087</span></div>
     *
     * ويتكفّل `justify-content: space-between` بدفع الطرفين إلى حافتَي
     * السطر. و TCPDF لا يعرف flexbox: يصيّر الوسمين متجاورين بلا فاصل،
     * فيخرج «رقم الفاتورة005087» — ومع الاتجاه من اليمين وأرقامٍ لاتينية
     * ينقلب ترتيبُ المقطعين فيصير النصُّ غيرَ مقروء.
     *
     * والجدولُ يفعل ما يفعله الصفُّ المرن وتفهمه TCPDF جيّداً. وأسماءُ
     * الأصناف تُقرأ من ورقة الأنماط نفسِها لا من قائمةٍ مكتوبة، فقالبٌ
     * جديد بصنفٍ جديد يعمل بلا تعديلٍ هنا.
     */
    protected function flexRowsToTables(string $html): string
    {
        preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $html, $styles);

        $classes = [];

        foreach ($styles[1] as $css) {
            // كل قاعدةٍ جسمُها `display:flex` — تُؤخذ أصنافُ مُحدِّدها
            preg_match_all('/([^{}]+)\{([^{}]*display\s*:\s*flex[^{}]*)\}/i', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                preg_match_all('/\.([A-Za-z0-9_-]+)/', $rule[1], $found);
                foreach ($found[1] as $class) {
                    $classes[$class] = true;
                }
            }
        }

        if ($classes === []) {
            return $html;
        }

        $pattern = '/<div([^>]*\bclass\s*=\s*"([^"]*)"[^>]*)>((?:(?!<div|<\/div>).)*?)<\/div>/si';

        // القوالبُ تُعشِّش هذه الصفوف داخل حاوياتها، فتمريرةٌ واحدة لا تكفي
        for ($pass = 0; $pass < 3; $pass++) {
            $next = preg_replace_callback($pattern, function ($match) use ($classes) {
                $rowClasses = preg_split('/\s+/', trim($match[2])) ?: [];

                if (! array_intersect($rowClasses, array_keys($classes))) {
                    return $match[0];
                }

                // خليّتان لا أكثر: ما عدا ذلك ليس سطرَ «تسمية ← قيمة»
                if (! preg_match_all('/<span[^>]*>(.*?)<\/span>/si', $match[3], $cells) || count($cells[1]) !== 2) {
                    return $match[0];
                }

                return '<table width="100%" cellpadding="0" cellspacing="0" class="'.$match[2].'"><tr>'
                    .'<td align="right">'.$cells[1][0].'</td>'
                    .'<td align="left">'.$cells[1][1].'</td>'
                    .'</tr></table>';
            }, $html);

            if ($next === null || $next === $html) {
                break;
            }

            $html = $next;
        }

        return $html;
    }

    /**
     * يُحوّل روابط الصور المحلّية إلى مسارات على القرص.
     *
     * **وإلّا سقط المرفق كلُّه.** الشعارُ في القوالب يُكتب رابطاً مطلقاً
     * (`{{business.logo}}` ← `http://…/assets/images/logo/logo.png`)،
     * و TCPDF يحاول جلبَه عبر HTTP فيفشل — والخادمُ لا يصل إلى نفسه من
     * سطر الأوامر، ولا حين يكون خلف مصادقةٍ أو جدارٍ ناريّ. وفشلُ صورةٍ
     * في TCPDF ليس تخطّياً لها بل خطأٌ يُسقط توليدَ الملف كلِّه، فتخرج
     * الرسالةُ بلا مستند وبلا سببٍ ظاهر للمستخدم.
     *
     * وجذرُ المستودع هو جذرُ الموقع في هذا النشر، فالمسارُ يُجرَّب تحت
     * `public/` ثم تحته مباشرةً. وما لا ملفَّ له يُترك كما هو: قد يكون
     * صورةً خارجيةً حقيقية، وتركُها يُبقي الأمرَ على ما كان.
     */
    protected function localizeImages(string $html): string
    {
        return preg_replace_callback('/<img\b[^>]*>/i', function ($match) {
            $tag = $match[0];

            if (! preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $tag, $src)) {
                return $tag;
            }

            $local = $this->localImagePath($src[1]);

            if ($local === $src[1]) {
                return $tag;
            }

            /* لا ملفَّ له ولا مضيفَ خارجيّ: يُنزَع الوسمُ كلُّه — مستندٌ
               بلا شعار خيرٌ من رسالةٍ بلا مستند، و`src=""` يُخطئ كذلك. */
            if ($local === null) {
                return '';
            }

            return str_replace($src[0], 'src="'.$local.'"', $tag);
        }, $html) ?? $html;
    }

    /**
     * مسارُ الصورة على القرص — أو الرابطُ كما هو إن كان خارجياً أو مضمَّناً،
     * أو `null` إن لم يوجد لها ملفّ.
     */
    protected function localImagePath(string $src): ?string
    {
        if ($src === '') {
            return null;
        }

        /* SVG لا تفهمها TCPDF: تُنزَع الصورةُ وحدها بدل أن يسقط الملفُّ
           كلُّه. ورمزُ الاستجابة والباركود صارا PNG عند مصدرهما
           (`DocumentTemplateDataService`)، فهذه شبكةُ أمانٍ لما يبقى من
           قوالبَ قديمةٍ أو صورةٍ يضعها المستخدم بنفسه. */
        if (str_starts_with($src, 'data:image/svg') || str_ends_with(strtok($src, '?'), '.svg')) {
            return null;
        }

        if (str_starts_with($src, 'data:') || is_file($src)) {
            return $src;
        }

        $path = $src;
        $appUrl = rtrim((string) config('app.url'), '/');

        foreach (array_filter([$appUrl, 'http://127.0.0.1:8000', 'http://localhost']) as $host) {
            if (str_starts_with($src, $host)) {
                $path = substr($src, strlen($host));
                break;
            }
        }

        /* رابطٌ لمضيفٍ آخر — يُنزَع. TCPDF يجلبه عبر الشبكة عند توليد
           الملف، وأيُّ تعثّرٍ (منفذٌ مغلق، مهلة، مضيفٌ لا يردّ) ليس تخطّياً
           للصورة بل خطأٌ يُسقط الملفَّ كلَّه — فيصل البريد بلا مستندٍ لأن
           موقعاً خارجياً كان بطيئاً. */
        if (preg_match('#^https?://#i', $path)) {
            return null;
        }

        $path = ltrim((string) parse_url($path, PHP_URL_PATH), '/');

        if ($path === '') {
            return null;
        }

        foreach ([public_path($path), base_path($path)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * يستبدل `var(--x)` بقيمتها من كتلة `:root`.
     *
     * TCPDF لا يعرف متغيّرات CSS: يترك `color: var(--primary-color)` بلا
     * تفسير فيسقط اللونُ والحجمُ والخطّ إلى المبدئيّ. والقيمُ مكتوبةٌ في
     * صدر المستند الذي يصيّره `DocumentTemplateRenderer`، فتُقرأ منه وتُحقن
     * مكانَها. وما لا قيمةَ له يُترك كما هو — استبدالُه بفراغٍ يُنتج
     * `color: ;` وهو أسوأ من تصريحٍ يُتجاهَل.
     */
    protected function resolveCssVariables(string $html): string
    {
        if (! preg_match('/:root\s*\{(.*?)\}/s', $html, $root)) {
            return $html;
        }

        preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', $root[1], $pairs, PREG_SET_ORDER);

        $values = [];
        foreach ($pairs as $pair) {
            $values[$pair[1]] = trim($pair[2]);
        }

        if ($values === []) {
            return $html;
        }

        return preg_replace_callback(
            '/var\(\s*--([\w-]+)\s*(?:,\s*([^)]*))?\)/',
            fn ($m) => $values[$m[1]] ?? (isset($m[2]) ? trim($m[2]) : $m[0]),
            $html
        );
    }

    /** اسمُ الملف المرفق — لاتينيٌّ محضاً: بعضُ عملاء البريد يكسرون العربية في الاسم */
    protected function fileName(string $documentType, string $number): string
    {
        $number = preg_replace('/[^A-Za-z0-9\-_]/', '', $number) ?: 'document';

        return str_replace('_', '-', $documentType).'-'.$number.'.pdf';
    }

    /** سطرُ سجلّ البريد — نجح أو فشل، ليبقى للرسالة أثر */
    protected function log(array $message, string $recipient, ?int $clientId, EmailStatus $status, ?string $error): void
    {
        try {
            EmailLog::create([
                'template_id' => $message['template_id'],
                'recipient_type' => RecipientType::Customer,
                'recipient_id' => $clientId,
                'recipient_email' => $recipient,
                'subject' => $message['subject'],
                'email_content' => $message['html'],
                'status' => $status,
                'sent_at' => $status === EmailStatus::Sent ? now() : null,
                'error_message' => $error,
            ]);
        } catch (\Throwable $e) {
            // سجلٌّ متعذّر لا يُبطل إرسالاً تمّ
            Log::warning('تعذّر كتابة سجلّ البريد', ['error' => $e->getMessage()]);
        }
    }

    /** @return array{ok: bool, error: string|null, email: string|null} */
    protected function failure(string $error, ?string $email): array
    {
        return ['ok' => false, 'error' => $error, 'email' => $email];
    }
}
