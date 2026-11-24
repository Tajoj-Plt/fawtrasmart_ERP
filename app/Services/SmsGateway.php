<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * إرسال الرسائل النصية، مع تسجيل كل محاولة وتطبيق إعدادات الإرسال.
 *
 * **لماذا خدمة مستقلة؟**
 *
 * كان الإرسال مكتوباً داخل `SmsController::sendTestSmsAjax` وحده: لا جزء
 * آخر في النظام يستطيع إرسال رسالة، ولا محاولة تُسجَّل — فشاشة «سجل
 * الرسائل» تبقى فارغة مهما أُرسل.
 *
 * **ولماذا كل هذه الفحوص قبل الإرسال؟**
 *
 * لأن الإعداد الذي لا يُقرأ ليس إعداداً. كل حقل في شاشة إعدادات الرسائل
 * له فحصٌ هنا يقابله: ساعات الهدوء تُراجَع قبل الاتصال بالبوابة، والسقف
 * اليومي يُحسب من السجل، ووضع الاختبار يمنع الخروج إلى الشبكة أصلاً.
 * ترتيب الفحوص مقصود — الأرخص أولاً، والاتصال بالبوابة آخرها.
 */
class SmsGateway
{
    /** رموز الأخطاء الشائعة من البوابة مترجمة */
    protected const ERRORS = [
        'M0000' => 'تم الإرسال بنجاح.',
        'M0001' => 'اسم المستخدم أو مفتاح الواجهة غير صحيح.',
        'M0002' => 'اسم المرسِل غير معتمد لدى البوابة.',
        'M0003' => 'الرصيد غير كافٍ لإرسال الرسالة.',
        'M0023' => 'رقم الجوال غير صحيح.',
    ];

    /** حالات السجل التي تعني «لم تُرسل ولن تُرسل» */
    public const BLOCKED_STATUSES = ['blocked', 'suppressed', 'capped'];

    public function settings(): ?object
    {
        $settings = SmsSetting::current();

        return $settings->is_active ? $settings : null;
    }

    /** الإعدادات كما هي، مفعّلة كانت أو لا — للشاشات لا للإرسال */
    public function config(): SmsSetting
    {
        return SmsSetting::current();
    }

    public function isConfigured(): bool
    {
        return SmsSetting::current()->isConfigured();
    }

    // ==================== الإرسال ====================

    /**
     * يرسل رسالة إلى رقم واحد بعد تطبيق كل إعدادات الإرسال.
     *
     * @return array{success: bool, message: string, log_id: int|null, status: string}
     */
    public function send(string $recipient, string $message, ?int $userId = null, ?string $templateCode = null): array
    {
        $settings = SmsSetting::current();

        // ---------- 1. البوابة مضبوطة ----------
        if (! $settings->isConfigured()) {
            return $this->reject($settings, 'إعدادات الرسائل غير مضبوطة أو معطّلة.', $recipient, $message, 'failed', $userId, $templateCode);
        }

        // ---------- 2. الرقم صالح ----------
        $normalized = $this->normalizeNumber($recipient, $settings);

        if ($normalized === null) {
            return $this->reject($settings, 'رقم الجوال غير صحيح — اكتبه بصيغة 05xxxxxxxx أو 9665xxxxxxxx.', $recipient, $message, 'failed', $userId, $templateCode);
        }

        // ---------- 3. القوائم: الممنوعة والمسموحة ----------
        if ($this->isBlocked($normalized, $settings)) {
            return $this->reject($settings, 'الرقم مدرَج في قائمة الأرقام الممنوعة.', $normalized, $message, 'blocked', $userId, $templateCode);
        }

        if ($settings->whitelist_enabled && ! $this->inList($normalized, $settings->allowedList())) {
            return $this->reject($settings, 'القائمة البيضاء مفعّلة، والرقم ليس ضمنها.', $normalized, $message, 'blocked', $userId, $templateCode);
        }

        // ---------- 4. صياغة النصّ النهائي ----------
        $body = $this->composeBody($message, $settings);

        if ($body === '') {
            return $this->reject($settings, 'نصّ الرسالة فارغ.', $normalized, $message, 'failed', $userId, $templateCode);
        }

        $segments = SmsTemplate::segmentsOf($body, $settings->encoding_mode);

        if ($settings->max_segments > 0 && $segments > $settings->max_segments) {
            return $this->reject(
                $settings,
                "الرسالة {$segments} أجزاء وتتجاوز الحدّ المسموح ({$settings->max_segments}).",
                $normalized, $body, 'blocked', $userId, $templateCode, $segments
            );
        }

        // ---------- 5. منع التكرار ----------
        if ($this->isDuplicate($normalized, $body, $settings)) {
            return $this->reject(
                $settings,
                "أُرسل النصّ نفسه لهذا الرقم خلال آخر {$settings->duplicate_window_minutes} دقيقة.",
                $normalized, $body, 'suppressed', $userId, $templateCode, $segments
            );
        }

        // ---------- 6. السقوف والمعدّل ----------
        if ($capMessage = $this->capExceeded($normalized, $settings)) {
            return $this->reject($settings, $capMessage, $normalized, $body, 'capped', $userId, $templateCode, $segments);
        }

        // ---------- 7. ساعات الهدوء وأيام العمل ----------
        if ($settings->inQuietHours() || ! $settings->isWorkingDay()) {
            return $this->handleQuietHours($settings, $normalized, $body, $userId, $templateCode, $segments);
        }

        // ---------- 8. وضع الاختبار ----------
        if ($settings->test_mode) {
            return $this->handleTestMode($settings, $normalized, $body, $userId, $templateCode, $segments);
        }

        // ---------- 9. الإرسال الفعلي ----------
        return $this->dispatch($settings, $normalized, $body, $userId, $templateCode, $segments);
    }

    /**
     * يرسل قالباً جاهزاً بعد تعبئة متغيّراته.
     *
     * @param  array<string, string|int|null>  $data
     */
    public function sendTemplate(string $code, string $recipient, array $data = [], ?int $userId = null): array
    {
        $template = SmsTemplate::active()->where('code', $code)->first();

        if (! $template) {
            return $this->reject(
                SmsSetting::current(),
                "لا يوجد قالب رسائل مفعَّل بالرمز «{$code}».",
                $recipient, '', 'failed', $userId, $code
            );
        }

        return $this->send($recipient, $template->render($data), $userId, $code);
    }

    /**
     * يرسل لحدثٍ لا لقالبٍ بعينه — القالب المربوط بالحدث هو ما يُرسل.
     *
     * هذا ما يجعل ربط القالب بحدث ذا معنى: يستدعي الكودُ الحدثَ، ويختار
     * المستخدمُ من الشاشة أيّ صياغة تُرسل عنده.
     */
    public function sendEvent(string $event, string $recipient, array $data = [], ?int $userId = null): array
    {
        $template = SmsTemplate::forEvent($event);

        if (! $template) {
            return $this->reject(
                SmsSetting::current(),
                "لا يوجد قالب مفعَّل مرتبط بالحدث «{$event}».",
                $recipient, '', 'failed', $userId, null
            );
        }

        return $this->send($recipient, $template->render($data), $userId, $template->code);
    }

    // ==================== الرصيد ====================

    /**
     * يسأل البوابة عن الرصيد — الفحص الوحيد الذي يثبت صحة بيانات الاتصال
     * دون استهلاك رسالة.
     *
     * @return array{success: bool, message: string, balance: string|null}
     */
    public function checkBalance(): array
    {
        $settings = SmsSetting::current();

        if (! $settings->isConfigured()) {
            return ['success' => false, 'message' => 'إعدادات الرسائل غير مضبوطة أو معطّلة.', 'balance' => null];
        }

        $url = $settings->balanceUrl();

        if (! $url) {
            return [
                'success' => false,
                'message' => 'المزوّد «'.$settings->providerLabel().'» لا يوفّر فحص رصيد.',
                'balance' => null,
            ];
        }

        try {
            $response = Http::timeout($settings->timeout_seconds ?: 20)->asJson()->post($url, [
                'userName' => $settings->username,
                'apiKey' => $settings->api_key,
            ]);

            $body = $response->json() ?: [];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'تعذّر الاتصال ببوابة الرسائل: '.$e->getMessage(),
                'balance' => null,
            ];
        }

        $balance = $body['balance'] ?? $body['Balance'] ?? null;

        if ($balance === null) {
            return ['success' => false, 'message' => $this->readableError($body), 'balance' => null];
        }

        $message = 'الاتصال بالبوابة سليم.';

        // التنبيه عند انخفاض الرصيد يقع هنا: هذه هي اللحظة الوحيدة التي
        // يُعرف فيها الرصيد فعلاً
        if ($settings->low_balance_alert && is_numeric($balance) && (float) $balance < $settings->low_balance_threshold) {
            $message .= " تنبيه: الرصيد ({$balance}) دون الحدّ المضبوط ({$settings->low_balance_threshold}).";
        }

        return ['success' => true, 'message' => $message, 'balance' => (string) $balance];
    }

    // ==================== توحيد الأرقام ====================

    /**
     * يوحّد صيغة الرقم حسب مفتاح الدولة المضبوط.
     *
     * كان المفتاح 966 مكتوباً في الكود، فالنظام لا يصلح لمنشأة خارج
     * السعودية. الآن المفتاح إعداد، والإرسال الدولي يُسمح به أو يُمنع
     * من الشاشة.
     */
    public function normalizeNumber(string $number, ?SmsSetting $settings = null): ?string
    {
        $settings ??= SmsSetting::current();

        $code = preg_replace('/\D+/', '', (string) $settings->default_country_code) ?: '966';
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if ($digits === '') {
            return null;
        }

        // 00 بادئة دولية، و0 بادئة محلية تُستبدل بمفتاح الدولة
        $digits = match (true) {
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, $code) => $digits,
            str_starts_with($digits, '0') => $code.substr($digits, 1),
            default => $digits,
        };

        // الطول المعقول لرقم دولي: 8 إلى 15 خانة (توصية E.164)
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        $local = str_starts_with($digits, $code);

        if (! $local) {
            if (! $settings->allow_international) {
                return null;
            }

            $allowed = array_filter((array) $settings->allowed_country_codes);

            if ($allowed !== [] && ! $this->matchesAnyPrefix($digits, $allowed)) {
                return null;
            }
        }

        return $digits;
    }

    // ==================== الفحوص ====================

    protected function isBlocked(string $number, SmsSetting $settings): bool
    {
        return $this->inList($number, $settings->blockedList());
    }

    /**
     * المطابقة على الرقم كاملاً أو على آخر تسع خانات.
     *
     * المستخدم يكتب القائمة كما اعتاد — «0501234567» أو «966501234567»
     * — ولا يُعقل أن يفشل المنع لاختلاف الصيغة.
     */
    protected function inList(string $number, array $list): bool
    {
        if ($list === []) {
            return false;
        }

        $tail = substr($number, -9);

        foreach ($list as $entry) {
            if ($entry === $number || substr($entry, -9) === $tail) {
                return true;
            }
        }

        return false;
    }

    protected function matchesAnyPrefix(string $digits, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            $prefix = preg_replace('/\D+/', '', (string) $prefix);

            if ($prefix !== '' && str_starts_with($digits, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function isDuplicate(string $recipient, string $body, SmsSetting $settings): bool
    {
        $window = (int) $settings->duplicate_window_minutes;

        if ($window <= 0) {
            return false;
        }

        return SmsLog::where('recipient', $recipient)
            ->where('message', $body)
            ->whereIn('status', ['sent', 'deferred', 'test'])
            ->where('created_at', '>=', now()->subMinutes($window))
            ->exists();
    }

    /**
     * أيّ سقف تجاوزناه — أو `null` إن كان الطريق سالكاً.
     *
     * لا تُحسب إلا الرسائل التي خرجت فعلاً: عدّ الرسائل الممنوعة ضمن
     * السقف يجعل خطأً واحداً يُغلق الإرسال بقية اليوم.
     */
    protected function capExceeded(string $recipient, SmsSetting $settings): ?string
    {
        $sent = fn () => SmsLog::whereIn('status', ['sent', 'test']);

        if ($settings->rate_limit_per_minute > 0) {
            $lastMinute = $sent()->where('created_at', '>=', now()->subMinute())->count();

            if ($lastMinute >= $settings->rate_limit_per_minute) {
                return "تجاوزنا حدّ {$settings->rate_limit_per_minute} رسالة في الدقيقة.";
            }
        }

        if ($settings->daily_cap > 0) {
            $today = $sent()->where('created_at', '>=', now()->startOfDay())->count();

            if ($today >= $settings->daily_cap) {
                return "بلغنا السقف اليومي ({$settings->daily_cap} رسالة).";
            }
        }

        if ($settings->monthly_cap > 0) {
            $month = $sent()->where('created_at', '>=', now()->startOfMonth())->count();

            if ($month >= $settings->monthly_cap) {
                return "بلغنا السقف الشهري ({$settings->monthly_cap} رسالة).";
            }
        }

        if ($settings->per_recipient_daily_cap > 0) {
            $toNumber = $sent()->where('recipient', $recipient)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();

            if ($toNumber >= $settings->per_recipient_daily_cap) {
                return "بلغ هذا الرقم حدّه اليومي ({$settings->per_recipient_daily_cap} رسائل).";
            }
        }

        return null;
    }

    // ==================== صياغة النصّ ====================

    /**
     * النصّ كما سيصل فعلاً: بعد التنظيف والتوقيع وجملة إيقاف الرسائل.
     *
     * الترتيب مهمّ — التوقيع وجملة الإيقاف تُضافان بعد التنظيف كي لا
     * يُقلَّم ما أضافه الإعداد.
     */
    public function composeBody(string $message, ?SmsSetting $settings = null): string
    {
        $settings ??= SmsSetting::current();
        $body = $message;

        if ($settings->strip_emoji) {
            $body = $this->stripEmoji($body);
        }

        if ($settings->collapse_whitespace) {
            // الأسطر تبقى، والمسافات المتكرّرة داخل السطر تُدمج: الرسالة
            // متعدّدة الأسطر مقصودة غالباً
            $body = (string) preg_replace('/[ \t]+/u', ' ', $body);
            $body = (string) preg_replace('/\n{3,}/u', "\n\n", $body);
        }

        $body = trim($body);

        if ($settings->append_signature && filled($settings->signature_text)) {
            $body .= "\n".trim($settings->signature_text);
        }

        if ($settings->append_opt_out && filled($settings->opt_out_text)) {
            $body .= "\n".trim($settings->opt_out_text);
        }

        return trim($body);
    }

    /** الرموز التعبيرية تُحوّل الرسالة إلى Unicode فتضاعف كلفتها */
    protected function stripEmoji(string $text): string
    {
        $cleaned = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}]/u', '', $text);

        return (string) preg_replace('/ {2,}/u', ' ', (string) $cleaned);
    }

    // ==================== المسارات ====================

    /**
     * ساعات الهدوء: يؤجَّل إلى أول وقت مسموح، أو يُلغى.
     *
     * التأجيل يكتب الرسالة بحالة `deferred` وموعدها، ويرسلها الأمر
     * `sms:flush` حين يحين — فالإعداد وعدٌ يُنفَّذ لا منعٌ صامت.
     */
    protected function handleQuietHours(SmsSetting $settings, string $recipient, string $body, ?int $userId, ?string $code, int $segments): array
    {
        if ($settings->quiet_hours_action === 'drop') {
            return $this->reject($settings, 'الوقت ضمن ساعات الهدوء، والإعداد يقضي بإلغاء الرسالة.', $recipient, $body, 'blocked', $userId, $code, $segments);
        }

        $next = $settings->nextAllowedTime();

        if ($next === null) {
            return $this->reject($settings, 'إعدادات ساعات الهدوء وأيام العمل لا تترك وقتاً مسموحاً للإرسال.', $recipient, $body, 'failed', $userId, $code, $segments);
        }

        $log = $this->log($settings, $recipient, $body, 'deferred', ['deferred_to' => $next->toDateTimeString()], $userId, $code, $segments, $next);

        return [
            'success' => true,
            'message' => 'الوقت ضمن ساعات الهدوء — أُجّلت الرسالة إلى '.$next->format('Y-m-d H:i').'.',
            'log_id' => $log?->id,
            'status' => 'deferred',
        ];
    }

    /**
     * وضع الاختبار: يُسجَّل ولا يخرج إلى الشبكة.
     *
     * إن ضُبط رقم تحويل، تُرسل الرسالة فعلاً إليه وحده — فيُختبر المسار
     * كاملاً دون أن يصل العملاءَ شيء.
     */
    protected function handleTestMode(SmsSetting $settings, string $recipient, string $body, ?int $userId, ?string $code, int $segments): array
    {
        $redirect = filled($settings->test_redirect_number)
            ? $this->normalizeNumber($settings->test_redirect_number, $settings)
            : null;

        if ($redirect === null) {
            $log = $this->log($settings, $recipient, $body, 'test', ['note' => 'وضع الاختبار — لم تُرسل'], $userId, $code, $segments);

            return [
                'success' => true,
                'message' => 'وضع الاختبار مفعَّل — سُجّلت الرسالة ولم تُرسل.',
                'log_id' => $log?->id,
                'status' => 'test',
            ];
        }

        $body = "[اختبار ← {$recipient}]\n".$body;

        $result = $this->dispatch($settings, $redirect, $body, $userId, $code, SmsTemplate::segmentsOf($body, $settings->encoding_mode));
        $result['message'] = 'وضع الاختبار — حُوّلت الرسالة إلى '.$redirect.'. '.$result['message'];

        return $result;
    }

    /** الاتصال بالبوابة وتسجيل نتيجته */
    protected function dispatch(SmsSetting $settings, string $recipient, string $body, ?int $userId, ?string $code, int $segments): array
    {
        $url = $settings->sendUrl();

        if (! $url) {
            return $this->reject($settings, 'لا توجد نقطة اتصال للمزوّد — اكتب العنوان في الإعدادات.', $recipient, $body, 'failed', $userId, $code, $segments);
        }

        try {
            $response = Http::timeout($settings->timeout_seconds ?: 20)
                ->asJson()
                ->post($url, $this->payload($settings, $recipient, $body));

            $result = $response->json() ?: ['raw' => $response->body()];
        } catch (\Throwable $e) {
            return $this->afterFailure(
                $settings,
                'تعذّر الاتصال ببوابة الرسائل: '.$e->getMessage(),
                $recipient, $body, $userId, $code, $segments, ['error' => $e->getMessage()]
            );
        }

        if ($this->succeeded($settings, $result)) {
            $log = $this->log($settings, $recipient, $body, 'sent', $result, $userId, $code, $segments);

            return ['success' => true, 'message' => 'تم إرسال الرسالة.', 'log_id' => $log?->id, 'status' => 'sent'];
        }

        return $this->afterFailure($settings, $this->readableError($result), $recipient, $body, $userId, $code, $segments, $result);
    }

    /**
     * صيغة الطلب تختلف بين المزوّدين — والاختلاف هنا وحده.
     */
    protected function payload(SmsSetting $settings, string $recipient, string $body): array
    {
        return match ($settings->provider) {
            'taqnyat' => [
                'recipients' => $recipient,
                'sender' => $settings->sender_name,
                'body' => $body,
                'bearerTokens' => $settings->api_key,
            ],
            'unifonic' => [
                'AppSid' => $settings->api_key,
                'SenderID' => $settings->sender_name,
                'Recipient' => $recipient,
                'Body' => $body,
            ],
            default => [
                'userName' => $settings->username,
                'numbers' => $recipient,
                'userSender' => $settings->sender_name,
                'apiKey' => $settings->api_key,
                'msg' => $body,
            ],
        };
    }

    /** علامة النجاح تختلف بين المزوّدين أيضاً */
    protected function succeeded(SmsSetting $settings, array $body): bool
    {
        return match ($settings->provider) {
            'taqnyat' => in_array((string) ($body['statusCode'] ?? ''), ['201', '200'], true),
            'unifonic' => ($body['success'] ?? null) === true || (string) ($body['success'] ?? '') === 'true',
            default => (string) ($body['code'] ?? '') === '1',
        };
    }

    /**
     * ما بعد الفشل: جدولة إعادة محاولة، وتنبيه المسؤولين عند التكرار.
     */
    protected function afterFailure(SmsSetting $settings, string $reason, string $recipient, string $body, ?int $userId, ?string $code, int $segments, array $response): array
    {
        $status = 'failed';
        $scheduled = null;

        if ($settings->retry_enabled && $settings->retry_attempts > 0) {
            $status = 'retry';
            $scheduled = now()->addSeconds(max(5, (int) $settings->retry_delay_seconds));
            $reason .= ' — ستُعاد المحاولة في '.$scheduled->format('H:i').'.';
        }

        $log = $this->log($settings, $recipient, $body, $status, $response + ['error' => $reason], $userId, $code, $segments, $scheduled);

        $this->checkFailureStreak($settings);

        return ['success' => false, 'message' => $reason, 'log_id' => $log?->id, 'status' => $status];
    }

    /**
     * تنبيه المسؤولين عند تتابع الإخفاقات.
     *
     * التنبيه يُكتب في جدول الإشعارات لا في البريد مباشرةً: بوابة الرسائل
     * متعطّلة أصلاً في هذه اللحظة، وقناةُ تنبيهٍ ثانية معطّلةٌ مثلها لا
     * تنفع. القناة الفعلية تُحدَّد من «إعدادات الإشعارات».
     */
    protected function checkFailureStreak(SmsSetting $settings): void
    {
        $threshold = (int) $settings->failure_threshold;

        if (! $settings->notify_on_failure || $threshold <= 0) {
            return;
        }

        try {
            $recent = SmsLog::latest('id')->limit($threshold)->pluck('status');

            if ($recent->count() < $threshold || $recent->contains(fn ($s) => in_array($s, ['sent', 'test'], true))) {
                return;
            }

            // إشعار واحد لكل موجة: التنبيه عند كل رسالة فاشلة يغرق الصندوق
            $alreadyWarned = \App\Models\notifications::where('type', 'sms_gateway_failing')
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            if ($alreadyWarned) {
                return;
            }

            // النموذج الصغير `notifications` هو المربوط ببوابة إعدادات
            // الإشعارات؛ `Notification` نموذجٌ قديم لجدول آخر
            \App\Models\notifications::create([
                'type' => 'sms_gateway_failing',
                'title' => 'بوابة الرسائل تفشل',
                'description' => "فشلت آخر {$threshold} رسائل متتالية. راجع بيانات البوابة والرصيد.",
                'user_id' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            // التنبيه لا يجوز أن يُسقط الإرسال
        }
    }

    // ==================== التسجيل ====================

    /**
     * يسجّل محاولة لم تخرج إلى الشبكة ويعيد نتيجتها.
     */
    protected function reject(SmsSetting $settings, string $reason, string $recipient, string $body, string $status, ?int $userId, ?string $code, int $segments = 0): array
    {
        $log = $this->log($settings, $recipient, $body, $status, ['error' => $reason], $userId, $code, $segments);

        return ['success' => false, 'message' => $reason, 'log_id' => $log?->id, 'status' => $status];
    }

    /**
     * التسجيل لا يجوز أن يُسقِط الإرسال — يُبتلع خطؤه ويُعاد `null`.
     *
     * إخفاء الرقم وحجب النصّ إعدادان: سجلّ الرسائل يُقرأ من موظفين لا
     * يلزم أن يطّلعوا على أرقام العملاء ولا على ما أُرسل إليهم.
     */
    protected function log(SmsSetting $settings, string $recipient, string $body, string $status, array $response, ?int $userId, ?string $code = null, int $segments = 0, ?Carbon $scheduledAt = null): ?SmsLog
    {
        if (! $settings->log_enabled) {
            return null;
        }

        try {
            return SmsLog::create([
                'user_id' => $userId ?: auth()->id(),
                'recipient' => $settings->mask_recipient ? $this->mask($recipient) : $recipient,
                'message' => $settings->log_body ? $body : '[حُجب النصّ بإعدادات السجل]',
                'template_code' => $code,
                'segments' => $segments,
                'cost' => in_array($status, ['sent', 'test'], true) ? $settings->costOf($segments) : 0,
                'status' => $status,
                'scheduled_at' => $scheduledAt,
                'attempts' => in_array($status, self::BLOCKED_STATUSES, true) ? 0 : 1,
                'gateway_response' => json_encode($response, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** 966501234567 ← 96650***4567 */
    protected function mask(string $number): string
    {
        $length = strlen($number);

        if ($length <= 8) {
            return $number;
        }

        return substr($number, 0, 5).str_repeat('*', $length - 9).substr($number, -4);
    }

    protected function readableError(array $body): string
    {
        $code = (string) ($body['code'] ?? '');

        if (isset(self::ERRORS[$code])) {
            return self::ERRORS[$code];
        }

        return 'فشل الإرسال: '.($body['message'] ?? $body['errors'] ?? $body['raw'] ?? 'استجابة غير مفهومة من البوابة');
    }
}
