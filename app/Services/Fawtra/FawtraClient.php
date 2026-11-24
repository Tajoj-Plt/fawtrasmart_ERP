<?php

namespace App\Services\Fawtra;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * عميل واجهة فوترة — طلبات موقَّعة بين خادم وخادم.
 *
 * **لماذا توقيع لا رمز حامل؟** (منقولٌ عن دليل الواجهة، لأن من يقرأ هذا
 * الملف يحتاج أن يعرف لماذا هو بهذا التعقيد): الرمز الحامل يُرسَل كما هو،
 * فمن اعترضه ملكه إلى أن يُلغى. أمّا التوقيع فلا يكشف السرّ — السرّ لا يغادر
 * هذا الخادم — ويرتبط بطلبه وحده، ويُستهلك مرّة واحدة.
 *
 * **النصّ المُوقَّع خمسة أسطر بالضبط**، مفصولة بـ`\n` بلا مسافات ولا سطر
 * سادس. أي حرف زائد يُنتج توقيعاً مرفوضاً برسالة `401` واحدة لا تقول أين
 * الخطأ — فالترتيب أدناه ليس اجتهاداً بل نصّ المواصفة:
 *
 *     METHOD
 *     PATH_WITH_QUERY
 *     TIMESTAMP
 *     NONCE
 *     SHA256_HEX(BODY)
 */
class FawtraClient
{
    /** تجزئة النصّ الفارغ — قيمة ثابتة لطلبات GET، مذكورة في الوثيقة. */
    public const EMPTY_BODY_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public function __construct(
        protected string $baseUrl,
        protected string $clientId,
        protected string $secret,
        protected string $domain,
    ) {}

    /**
     * بناء العميل من الإعداد — أو null إن لم يكتمل الربط بعد.
     */
    public static function fromConfig(): ?self
    {
        $credentials = app(FawtraCredentials::class);

        if (! $credentials->configured()) {
            return null;
        }

        return new self(
            rtrim((string) config('fawtra.base_url'), '/'),
            (string) config('fawtra.client_id'),
            (string) config('fawtra.secret'),
            (string) config('fawtra.domain'),
        );
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array{status:int, ok:bool, data:array, error:?array, meta:array, raw:?string, transport_error:?string}
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query, null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(string $path, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->send('POST', $path, [], $body, $idempotencyKey);
    }

    /**
     * تنفيذ الطلب الموقَّع.
     */
    protected function send(string $method, string $path, array $query, ?array $body, ?string $idempotencyKey = null): array
    {
        $method = strtoupper($method);
        $path = config('fawtra.prefix', '/api/integration/v1/').ltrim($path, '/');

        // سلسلة الاستعلام داخلة في التوقيع، فيجب أن تُبنى مرّة واحدة
        // وتُستعمل في التوقيع وفي عنوان الطلب معاً — بناؤها مرّتين يفتح باب
        // اختلاف الترتيب أو الترميز فيسقط التوقيع
        $fullPath = $query !== [] ? $path.'?'.http_build_query($query) : $path;

        // JSON_UNESCAPED_UNICODE مطابقٌ للمثال في الوثيقة: التجزئة تقع على
        // النصّ الذي سيُرسل حرفياً، فأي اختلاف ترميز يُبطل التوقيع
        $content = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE);

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(12));

        $canonical = implode("\n", [
            $method,
            $fullPath,
            $timestamp,
            $nonce,
            $content === '' ? self::EMPTY_BODY_HASH : hash('sha256', $content),
        ]);

        $headers = [
            'X-Fawtra-Key: '.$this->clientId,
            'X-Fawtra-Timestamp: '.$timestamp,
            'X-Fawtra-Nonce: '.$nonce,
            'X-Fawtra-Signature: '.hash_hmac('sha256', $canonical, $this->secret),
            'X-Fawtra-Domain: '.$this->domain,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: '.$idempotencyKey;
        }

        return $this->execute($method, $this->baseUrl.$fullPath, $headers, $content);
    }

    /**
     * تنفيذ نداء cURL وتطبيع الاستجابة.
     *
     * الاستجابة مطبَّعة دائماً إلى الشكل نفسه — نجحت أم أخفقت أم انقطعت
     * الشبكة — كي لا يحتاج كل مستدعٍ أن يفحص ثلاث حالات مختلفة.
     */
    protected function execute(string $method, string $url, array $headers, string $content): array
    {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int) config('fawtra.timeout', 15),
            CURLOPT_CONNECTTIMEOUT => 10,

            // تحقّق الشهادة إلزامي: تعطيله يُبطل حماية النقل كلّها ويجعل
            // التوقيع بلا معنى — من يعترض الاتصال يقرأ السرّ في الترويسات
            CURLOPT_SSL_VERIFYPEER => (bool) config('fawtra.verify_ssl', true),
            CURLOPT_SSL_VERIFYHOST => config('fawtra.verify_ssl', true) ? 2 : 0,
        ]);

        if ($content !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $content);
        }

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $transportError = curl_error($handle) ?: null;
        curl_close($handle);

        if ($raw === false) {
            return $this->failure(0, 'NETWORK_ERROR', $transportError ?: 'تعذّر الاتصال بخادم فوترة.', null, $transportError);
        }

        try {
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            // 304 ترجع بلا جسم — وهي نجاحٌ لا خطأ
            if ($status === 304) {
                return ['status' => 304, 'ok' => true, 'data' => [], 'error' => null, 'meta' => [], 'raw' => null, 'transport_error' => null];
            }

            return $this->failure($status, 'INVALID_RESPONSE', 'استجابة غير مفهومة من خادم فوترة.', (string) $raw, null);
        }

        if (! is_array($decoded)) {
            return $this->failure($status, 'INVALID_RESPONSE', $this->describeStatus($status, $url), (string) $raw, null);
        }

        $ok = ($decoded['success'] ?? false) === true && $status >= 200 && $status < 300;

        if (! $ok) {
            $error = $decoded['error'] ?? [];

            // **استجابة بلا غلاف `error` ليست خطأً من الواجهة بل من العنوان.**
            //
            // كل مسارات فوترة تُرجع `{success, error:{code, message}}`. فردٌّ
            // بلا هذا الغلاف يعني أن الطلب لم يصل إلى الواجهة أصلاً: 404 من
            // Laravel، أو صفحة وسيط، أو عنوان خاطئ. وإرجاع «خطأ غير معروف»
            // هنا يترك المستخدم بلا دليل — وقد وقع فعلاً: عنوانٌ أُدخل
            // متضمّناً بادئة الواجهة أنتج 404 ورسالةً لا تقول شيئاً.
            $code = $error['code'] ?? null;

            if ($code === null) {
                return $this->failure($status, 'INVALID_RESPONSE', $this->describeStatus($status, $url), null, null);
            }

            return [
                'status' => $status,
                'ok' => false,
                'data' => [],
                'error' => [
                    'code' => $code,
                    'message' => $error['message'] ?? 'خطأ غير معروف من خادم فوترة.',
                    'fields' => $error['fields'] ?? null,
                    'retry_after' => $error['retry_after'] ?? null,
                ],
                'meta' => $decoded['meta'] ?? [],
                'raw' => null,
                'transport_error' => null,
            ];
        }

        return [
            'status' => $status,
            'ok' => true,
            'data' => $decoded['data'] ?? [],
            'error' => null,
            'meta' => $decoded['meta'] ?? [],
            'raw' => null,
            'transport_error' => null,
        ];
    }

    /**
     * وصف حالة HTTP بما يدلّ على السبب لا بما يصفه حرفياً.
     *
     * الردّ الذي لا يحمل غلاف فوترة يعني غالباً أن العنوان خاطئ أو أن أمامه
     * وسيطاً — لا أن الواجهة رفضت. فالرسالة تقول أين يبحث.
     */
    protected function describeStatus(int $status, string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '—';

        return match (true) {
            $status === 404 => "المسار غير موجود على «{$host}» (404). تأكّد أن عنوان اللوحة لا يتضمّن /api/integration/v1 — العميل يضيفها بنفسه.",
            $status === 0 => "تعذّر الوصول إلى «{$host}». تحقّق من الاتصال ومن صحّة العنوان.",
            $status === 401 || $status === 403 => "رفض الخادم الطلب ({$status}) بردٍّ غير متوقَّع. قد يكون أمام اللوحة جدارُ حماية أو وسيط.",
            $status >= 500 => "خطأ في خادم فوترة ({$status}). ستُعاد المحاولة تلقائياً.",
            $status >= 300 && $status < 400 => "العنوان يعيد التوجيه ({$status}). استعمل العنوان النهائي مباشرةً.",
            default => "استجابة غير مفهومة من «{$host}» (رمز {$status}).",
        };
    }

    protected function failure(int $status, string $code, string $message, ?string $raw, ?string $transportError): array
    {
        Log::warning('فوترة: إخفاق نداء', ['code' => $code, 'status' => $status, 'message' => $message]);

        return [
            'status' => $status,
            'ok' => false,
            'data' => [],
            'error' => ['code' => $code, 'message' => $message, 'fields' => null, 'retry_after' => null],
            'meta' => [],
            'raw' => $raw,
            'transport_error' => $transportError,
        ];
    }
}
