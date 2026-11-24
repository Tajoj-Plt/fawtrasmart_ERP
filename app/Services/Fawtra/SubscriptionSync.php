<?php

namespace App\Services\Fawtra;

use App\Models\FawtraAlert;
use App\Models\FawtraSubscription;
use App\Services\SystemAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مزامنة الاشتراك مع لوحة فوترة.
 *
 * تُنفّذ ما توصي به الوثيقة (القسم ٦):
 *
 * - `GET /sync?sync_token=…` كل ١٥ دقيقة. فإن لم يتغيّر شيء تعود الاستجابة
 *   مختصرة `{changed:false}` — فلا معالجة ولا كتابة.
 * - تُخزَّن آخر لقطة ناجحة، ويُعمل بها عند الانقطاع. الوثيقة صريحة: «لا توقف
 *   نظام العميل لعطل شبكة عندنا».
 * - `401`/`403` لا يُعاد معها في حلقة — ثمانية إخفاقات متتالية تقفل المفتاح
 *   ربع ساعة عند فوترة. فالتراجع الأسّي هنا ليس تحسيناً بل شرطُ ألّا يقفل
 *   النظامُ مفتاحَ نفسه.
 */
class SubscriptionSync
{
    /**
     * مزامنة كاملة.
     *
     * @param  bool  $force  تجاهل رمز المزامنة وفترة التراجع — لزرّ «زامن الآن»
     * @return array{ok:bool, changed:bool, message:string, code:?string}
     */
    public function run(bool $force = false): array
    {
        $client = FawtraClient::fromConfig();

        if ($client === null) {
            return $this->result(false, false, 'النظام غير مربوط بلوحة فوترة بعد.', 'NOT_LINKED');
        }

        $record = FawtraSubscription::current();

        // التراجع بعد الإخفاق يُحترم إلا عند الإجبار اليدوي
        if (! $force && $record && $record->retry_after && $record->retry_after->isFuture()) {
            return $this->result(
                false,
                false,
                'المزامنة مؤجَّلة حتى '.$record->retry_after->format('H:i').' بعد إخفاقات متتالية.',
                'BACKOFF'
            );
        }

        $query = [];

        // رمز المزامنة يوفّر المعالجة كلّها لا النقل وحده — يُرسَل ما لم يُطلب
        // التحديث الإجباري
        if (! $force && $record?->sync_token) {
            $query['sync_token'] = $record->sync_token;
        }

        $response = $client->get('sync', $query);

        if (! $response['ok']) {
            return $this->recordFailure($record, $response);
        }

        $data = $response['data'];

        // لا شيء تغيّر: تُحدَّث طوابع الزمن وحدها ويُصفَّر عدّاد الإخفاق.
        // والرفع يجري رغم ذلك — «لم يتغيّر شيء عندهم» لا يعني أن النظام لم
        // يُصدر فواتير عندنا منذ الدورة الماضية
        if (($data['changed'] ?? true) === false) {
            $this->recordSuccess($record, null, $data['sync_token'] ?? $record?->sync_token);

            if ($record) {
                $this->pushUsage($client, $record->refresh());
            }

            return $this->result(true, false, 'لا تغيير في الاشتراك.', null);
        }

        $record = $this->storeSnapshot($record, $data);

        // الرفع بعد حفظ اللقطة لا قبله: الحدود ونوع كل ميزة (عدّاد أم سقف)
        // تُقرأ من اللقطة الجديدة، ورفعٌ بحدودٍ قديمة قد يُرفض بلا سبب مفهوم
        $this->pushUsage($client, $record);

        $this->storeAlerts($data['alerts'] ?? [], $client);

        // تنبيهات الحدود تُولَّد محلياً أيضاً لا انتظاراً لفوترة: تنبيهها
        // يُنشأ عند ٨٠٪ ثم لا يتكرّر، وقد يتجاوز المستخدم العتبة بين مزامنتين
        // فلا يرى شيئاً حتى المزامنة التالية. والحدّ يُقاس من اللقطة نفسها
        $this->generateUsageAlerts($record);

        // كاش البوّابة يحمل الحدود القديمة — إبطاله هنا لا في الطلب التالي،
        // وإلا عملت الشاشات دقيقةً كاملة بأرقام قبل المزامنة
        SubscriptionGate::forget();

        return $this->result(true, true, 'حُدِّثت بيانات الاشتراك.', null);
    }

    /**
     * رفع الاستهلاك المحلّي — نصف المزامنة الآخر.
     *
     * **لماذا هنا لا في المتحكّمات؟** لأن البديل نداءٌ خارجيّ مع كل فاتورة:
     * يُبطئ الحفظ بزمن رحلة، ويستنفد حدّ الطلبات في منشأةٍ نشطة، ويوقف
     * إصدار الفواتير إن انقطعت الشبكة. فالقياس محلّي والرفع دوريّ.
     *
     * وإخفاق الرفع لا يُفشل المزامنة: اللقطة حُفظت والنظام يعمل، والرفع
     * تصحيحٌ تراكميّ يُعاد في الدورة التالية بلا فقد.
     */
    protected function pushUsage(FawtraClient $client, FawtraSubscription $record): void
    {
        if (! config('fawtra.usage.report', true)) {
            return;
        }

        try {
            $summary = app(UsageReporter::class)->push($client, $record);

            if (($summary['reported'] ?? 0) > 0 || ($summary['caps'] ?? 0) > 0) {
                Log::info('فوترة: رُفع الاستهلاك', $summary);
            }

            // ميزةٌ رُفض رفعها لبلوغ الحدّ: المستخدم يعمل بأكثر مما تسمح به
            // باقته، وهذا أوجب ما يُنبَّه عليه
            foreach ($summary['blocked'] ?? [] as $feature) {
                $this->notify(
                    SystemAlertService::SUBSCRIPTION_USAGE_EXHAUSTED,
                    'تجاوزت حدّ «'.$feature.'» في باقة فوترة',
                    'رفضت لوحة فوترة تسجيل الاستهلاك الإضافي لهذه الميزة في الفترة الحالية. اشترِ إضافة أو انتظر الفترة الجديدة.'
                );
            }
        } catch (Throwable $e) {
            Log::warning('فوترة: تعذّر رفع الاستهلاك', ['error' => $e->getMessage()]);
        }
    }

    /**
     * التحقّق من صحّة بيانات اعتماد قبل حفظها — يُستعمل في المعالج وفي الإعدادات.
     *
     * `/ping` أخفّ المسارات ولا يلمس الاشتراك، وهو المقصود بالفحص: هل
     * المفتاح والتوقيع والدومين مقبولة؟
     *
     * @return array{ok:bool, message:string, data:array}
     */
    public function verify(array $credentials): array
    {
        // العنوان من الإعداد لا من المدخلات — ويُطبَّع احتياطاً لمن كتبه
        // يدوياً في `.env` متضمّناً بادئة الواجهة
        $baseUrl = FawtraCredentials::normaliseBaseUrl(
            $credentials['base_url'] ?? config('fawtra.base_url')
        );

        if ($baseUrl === '') {
            return [
                'ok' => false,
                'message' => 'عنوان لوحة فوترة غير مضبوط. اضبط FAWTRA_BASE_URL في ملف .env.',
                'data' => [],
            ];
        }

        $client = new FawtraClient(
            $baseUrl,
            $credentials['client_id'],
            $credentials['secret'],
            $credentials['domain'],
        );

        $response = $client->get('ping');

        if ($response['ok']) {
            $data = $response['data'];

            return [
                'ok' => true,
                'message' => sprintf(
                    'تم الاتصال بنجاح — العميل «%s»، الدومين %s.',
                    $data['client']['name'] ?? '—',
                    $data['domain'] ?? '—'
                ),
                'data' => $data,
            ];
        }

        return [
            'ok' => false,
            'message' => $this->explain($response['error']['code'] ?? 'UNKNOWN', $response['error']['message'] ?? ''),
            'data' => [],
        ];
    }

    /* ==================== التخزين ==================== */

    protected function storeSnapshot(?FawtraSubscription $record, array $data): FawtraSubscription
    {
        $subscription = $data['subscription'] ?? [];

        $attributes = [
            'is_active' => (bool) ($subscription['is_active'] ?? false),
            'is_expired' => (bool) ($subscription['is_expired'] ?? false),
            'is_trial' => (bool) ($subscription['is_trial'] ?? false),
            'status' => $subscription['status'] ?? null,
            'end_date' => $subscription['end_date'] ?? null,
            'days_remaining' => $subscription['days_remaining'] ?? null,
            'auto_renew' => (bool) ($subscription['auto_renew'] ?? false),
            // البنيتان مقبولتان: `/sync` يُعيد `plan` مسطَّحاً
            // (`{id,name,type,permissions}`) بينما `/plan` يُعيده مغلَّفاً
            // (`{plan:{…}, limits:[…]}`). الاعتماد على واحدة يُنتج اسم باقة
            // فارغاً — وقد وقع فعلاً
            'plan_name' => $data['plan']['name'] ?? $data['plan']['plan']['name'] ?? null,
            'activation_serial' => $subscription['activation_serial'] ?? null,

            'snapshot' => [
                'account' => $data['account'] ?? [],
                'subscription' => $subscription,
                'plan' => $data['plan'] ?? [],
                'usage' => $data['usage'] ?? [],
                'billing' => $data['billing'] ?? [],
                'generated_at' => $data['generated_at'] ?? null,
            ],
            'sync_token' => $data['sync_token'] ?? null,

            'last_synced_at' => now(),
            'last_attempt_at' => now(),
            'last_error_code' => null,
            'last_error' => null,
            'consecutive_failures' => 0,
            'retry_after' => null,
        ];

        if ($record) {
            $record->update($attributes);

            return $record->refresh();
        }

        return FawtraSubscription::create($attributes);
    }

    /**
     * حفظ التنبيهات والإقرار بها.
     *
     * الإقرار يُرسَل بعد الحفظ المحلي لا قبله: لو انقطعت الشبكة بينهما بقي
     * التنبيه ظاهراً للمستخدم — وهو الخطأ الأهون. والعكس يخفيه محلياً وقد لم
     * يصل الإقرار.
     */
    protected function storeAlerts(array $alerts, FawtraClient $client): void
    {
        foreach ($alerts as $alert) {
            $remoteId = $alert['id'] ?? null;

            if ($remoteId === null) {
                continue;
            }

            $stored = FawtraAlert::updateOrCreate(
                ['remote_id' => $remoteId],
                [
                    'type' => $alert['type'] ?? 'unknown',
                    'severity' => $alert['severity'] ?? 'warning',
                    'title' => $alert['title'] ?? '',
                    'body' => $alert['body'] ?? $alert['message'] ?? null,
                    'payload' => $alert,
                    'remote_created_at' => isset($alert['created_at'])
                        ? Carbon::parse($alert['created_at'])
                        : null,
                ]
            );

            // تنبيه فوترة الجديد يظهر في الجرس أيضاً: قرب الانتهاء أو دفعة
            // مستحقّة قرارٌ إداري لا يُكتشف بفتح شاشة الإعدادات مصادفةً
            if ($stored->wasRecentlyCreated) {
                $this->notify(
                    SystemAlertService::SUBSCRIPTION_STATUS,
                    $stored->title !== '' ? $stored->title : $stored->typeLabel(),
                    (string) $stored->body
                );
            }

            if ($stored->acknowledged_at !== null) {
                continue;
            }

            try {
                $response = $client->post("alerts/{$remoteId}/ack");

                if ($response['ok']) {
                    $stored->update(['acknowledged_at' => now()]);
                }
            } catch (Throwable $e) {
                // الإقرار ليس حرجاً — التنبيه محفوظ ويُقرّ به في المزامنة التالية
                Log::info('فوترة: تعذّر الإقرار بالتنبيه '.$remoteId);
            }
        }
    }

    /**
     * تنبيهات حدود الاستخدام — تُولَّد محلياً من اللقطة.
     *
     * **لماذا محلياً وفوترة تُرسل تنبيهاتها؟** لأن تنبيه فوترة يُنشأ لحظة
     * بلوغ العتبة عندها، ونحن نعرف الاستهلاك بين مزامنتين قبل أن تعرفه هي.
     * فمن بلغ ٩٥٪ بعد المزامنة الأخيرة يجب أن يرى تنبيهاً الآن لا بعد ربع
     * ساعة — والحدّ الذي يُبلغه لا رجعة فيه.
     *
     * **ولا يتكرّر التنبيه:** المعرّف مشتقّ من الميزة والعتبة والفترة، فبلوغ
     * ٨٠٪ يُنشئ تنبيهاً واحداً لا واحداً كل ربع ساعة. وتغيّر الفترة (شهر
     * جديد) يُنشئ معرّفاً جديداً فيظهر التنبيه من جديد — وهو المطلوب.
     */
    protected function generateUsageAlerts(FawtraSubscription $record): void
    {
        // العتبات تنازلياً: من بلغ ١٠٠٪ لا يحتاج تنبيه ٨٠٪ أيضاً
        $thresholds = [100 => 'critical', 80 => 'warning'];

        $period = now()->format('Y-m');

        foreach ($record->usage() as $item) {
            if (($item['unlimited'] ?? false) || ! isset($item['key'])) {
                continue;
            }

            $percent = (int) ($item['percent'] ?? 0);

            foreach ($thresholds as $threshold => $severity) {
                if ($percent < $threshold) {
                    continue;
                }

                $this->recordUsageAlert($item, $threshold, $severity, $period);

                break;
            }
        }
    }

    /**
     * تسجيل تنبيه حدٍّ محلي.
     *
     * البصمة `usage:الميزة:العتبة:الفترة` هي ما يمنع التكرار بلا جدول إضافي:
     * نفس الميزة عند نفس العتبة في نفس الشهر تنبيهٌ واحد. وتغيّر الشهر يغيّر
     * البصمة فيظهر التنبيه من جديد — وهو المطلوب.
     */
    protected function recordUsageAlert(array $item, int $threshold, string $severity, string $period): void
    {
        $key = $item['key'];
        $fingerprint = 'usage:'.$key.':'.$threshold.':'.$period;
        $label = $item['label'] ?? $key;

        $alert = FawtraAlert::firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'remote_id' => null,
                'source' => 'local',
                'type' => $threshold >= 100 ? 'usage.exhausted' : 'usage.threshold',
                'severity' => $severity,
                'title' => $threshold >= 100
                    ? 'استُنفد حدّ «'.$label.'»'
                    : 'بلغت '.$threshold.'٪ من حدّ «'.$label.'»',
                'body' => sprintf(
                    'الاستهلاك %s من %s (%s٪). %s',
                    $item['used'] ?? 0,
                    $item['allowance'] ?? 0,
                    $item['percent'] ?? 0,
                    $threshold >= 100
                        ? 'اشترِ إضافة أو انتظر بداية الفترة الجديدة.'
                        : 'راقب الاستهلاك أو اشترِ إضافة قبل بلوغ الحدّ.'
                ),
                'payload' => ['local' => true, 'feature' => $key, 'threshold' => $threshold, 'period' => $period] + $item,
                'remote_created_at' => now(),

                // مُقرٌّ به محلياً: لا يُرسَل إلى فوترة لأنه ليس تنبيهها
                'acknowledged_at' => now(),
            ]
        );

        // التنبيه يخرج إلى جرس الإشعارات مرّةً واحدة — عند إنشائه لا عند كل
        // مزامنة. وشاشة الاشتراك وحدها لا تكفي: من يبلغ حدّه لا يزورها
        if ($alert->wasRecentlyCreated) {
            $this->notify(
                $threshold >= 100
                    ? SystemAlertService::SUBSCRIPTION_USAGE_EXHAUSTED
                    : SystemAlertService::SUBSCRIPTION_USAGE_THRESHOLD,
                $alert->title,
                (string) $alert->body
            );
        }
    }

    /**
     * تمرير التنبيه إلى قناة إشعارات النظام القائمة.
     *
     * لا قناة جديدة ولا `View::composer` عامّ: `SystemAlertService` يكتب في
     * جدول `notifications` نفسه، فيظهر التنبيه في الجرس وتنطبق عليه إعدادات
     * شاشة الإشعارات (التفعيل والقنوات والمستلمون) بلا تعديل فيها.
     *
     * والفشل هنا لا يُسقط المزامنة: لقطةٌ حُفظت وتنبيهٌ لم يُرسَل أهون من
     * مزامنة فاشلة.
     */
    protected function notify(string $type, string $title, string $body = ''): void
    {
        try {
            SystemAlertService::send($type, $title, $body);
        } catch (Throwable $e) {
            Log::warning('فوترة: تعذّر إرسال إشعار التنبيه', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }

    protected function recordSuccess(?FawtraSubscription $record, ?array $snapshot, ?string $token): void
    {
        if (! $record) {
            return;
        }

        $record->update([
            'last_synced_at' => now(),
            'last_attempt_at' => now(),
            'sync_token' => $token ?: $record->sync_token,
            'last_error_code' => null,
            'last_error' => null,
            'consecutive_failures' => 0,
            'retry_after' => null,
        ]);
    }

    /**
     * رموز الإخفاق التي تعني رفضاً قاطعاً من فوترة — لا عطلاً عابراً في
     * الشبكة. تُقفل الاشتراك محلياً فورها بدل الاستمرار على لقطة «نشط»
     * قديمة قد لا تعود صحيحة أبداً.
     */
    protected const LOCK_IMMEDIATELY = ['SUBSCRIPTION_UNAVAILABLE', 'ACCOUNT_DISABLED', 'DOMAIN_NOT_ALLOWED'];

    /**
     * هل يُقفل الاشتراك محلياً بهذا الإخفاق؟
     *
     * `SUBSCRIPTION_UNAVAILABLE`/`ACCOUNT_DISABLED`/`DOMAIN_NOT_ALLOWED` لا
     * لبس فيها فتُقفل من أوّل إخفاق. أمّا `UNAUTHORIZED` فتُستعمل — كما
     * توثّق `explain()` — لكل أسباب المصادقة معاً (سرّ خاطئ، ساعة خادم
     * منحرفة، أو اشتراك حُذف فعلاً)، فقفلٌ من أوّل إخفاقٍ بها قد يقفل نظاماً
     * حيّاً بسبب انحراف ساعة عابر. تُقفل بعد بلوغ عتبة التنبيه نفسها
     * (افتراضياً ٣ إخفاقات متتالية) — رفضٌ يتكرّر بهذا القدر لم يعد عابراً.
     */
    protected function shouldLock(string $code, int $failures): bool
    {
        if (in_array($code, self::LOCK_IMMEDIATELY, true)) {
            return true;
        }

        if ($code === 'UNAUTHORIZED') {
            return $failures >= (int) config('fawtra.sync.max_failures_before_alert', 3);
        }

        return false;
    }

    /**
     * تسجيل الإخفاق وحساب موعد المحاولة التالية.
     */
    protected function recordFailure(?FawtraSubscription $record, array $response): array
    {
        $code = $response['error']['code'] ?? 'UNKNOWN';
        $message = $this->explain($code, $response['error']['message'] ?? '');

        $failures = ($record?->consecutive_failures ?? 0) + 1;

        // `429` يحمل مهلته الخاصة؛ وما عداه يتبع التراجع الأسّي المُعَدّ
        $backoff = config('fawtra.sync.backoff', [60, 300, 900, 1800, 3600]);
        $seconds = $response['error']['retry_after']
            ?? $backoff[min($failures - 1, count($backoff) - 1)];

        $wasActive = (bool) $record?->is_active;

        $attributes = [
            'last_attempt_at' => now(),
            'last_error_code' => $code,
            'last_error' => $message,
            'consecutive_failures' => $failures,
            'retry_after' => now()->addSeconds((int) $seconds),
        ];

        // القفل يعمل على سجلٍّ موجود له لقطة «نشط» سابقة فقط: سجلٌّ جديد
        // يُنشأ افتراضياً بـ`is_active=false`، والوسيط يمنع أصلاً بغياب
        // اللقطة — فلا حاجة لقفلٍ لا يغيّر شيئاً
        $locking = $record && $this->shouldLock($code, $failures);

        if ($locking) {
            $attributes['is_active'] = false;
        }

        if ($record) {
            $record->update($attributes);
        } else {
            FawtraSubscription::create($attributes);
        }

        // كاش البوّابة يحمل «نشط» قديمة — إبطاله فور القفل لا بعد دقيقة كاملة
        if ($locking) {
            SubscriptionGate::forget();
        }

        // الانتقال إلى القفل يُنبَّه عليه فور وقوعه لا عند دورةٍ تالية —
        // نظامٌ فقد اشتراكه يجب أن يعلم به مشغّله الآن. وإخفاقٌ لا يُقفل
        // (عطل شبكة، حدّ طلبات) يتبع العتبة القديمة نفسها كي لا يُغرق
        // المشغّل بتنبيهٍ عند كل إخفاق
        if ($locking && $wasActive) {
            Log::error('فوترة: قُفل الاشتراك محلياً بعد رفضٍ قاطع', ['code' => $code, 'failures' => $failures]);

            $this->notify(
                SystemAlertService::SUBSCRIPTION_STATUS,
                'قُفل الاشتراك محلياً — تعذّرت المصادقة مع فوترة',
                $message.' يحاول النظام إعادة الاتصال تلقائياً وفق التراجع الأسّي المُعَدّ.'
            );
        } elseif ($failures === (int) config('fawtra.sync.max_failures_before_alert', 3)) {
            Log::error('فوترة: تعثّرت المزامنة '.$failures.' مرّات متتالية', ['code' => $code]);

            $this->notify(
                SystemAlertService::SUBSCRIPTION_STATUS,
                'تعثّرت مزامنة الاشتراك مع لوحة فوترة',
                $this->explain($code, $message).' — يعمل النظام حالياً بآخر لقطة محفوظة.'
            );
        }

        return $this->result(false, false, $message, $code);
    }

    /* ==================== أدوات ==================== */

    /**
     * ترجمة رمز الخطأ إلى ما يفعله المشغّل — لا إلى وصفٍ حرفي.
     *
     * رسالة «401 UNAUTHORIZED» لا تفيد من يقرؤها؛ والوثيقة تذكر عمداً أن
     * `401` واحدة لكل أسباب المصادقة كي لا تُعطي المُخمّن خريطة. فالمفيد أن
     * نقول له أين يبحث.
     */
    protected function explain(string $code, string $fallback): string
    {
        return match ($code) {
            'UNAUTHORIZED' => 'رُفضت المصادقة. تحقّق من معرّف العميل والسرّ، ومن أن ساعة الخادم مضبوطة (فرقٌ يتجاوز ٥ دقائق يُبطل التوقيع).',
            'BROWSER_BLOCKED' => 'الواجهة بين خادم وخادم ولا تُفتح من متصفّح.',
            'DOMAIN_NOT_ALLOWED' => 'الدومين المسجَّل لا يطابق دومين هذا الخادم. راجع تسجيل المفتاح في لوحة فوترة.',
            'ACCOUNT_DISABLED' => 'حساب العميل موقوف عند فوترة. راجع مزوّد الخدمة.',
            'SUBSCRIPTION_UNAVAILABLE' => 'الاشتراك لم يعد موجوداً. راجع إدارة فوترة.',
            'RATE_LIMITED' => 'تجاوز النظام حدّ الطلبات المسموح. ستُعاد المحاولة تلقائياً.',
            'NETWORK_ERROR' => 'تعذّر الوصول إلى خادم فوترة. يعمل النظام بآخر لقطة محفوظة.',
            'INVALID_RESPONSE' => 'استجابة غير مفهومة من خادم فوترة.',
            'NOT_LINKED' => 'النظام غير مربوط بلوحة فوترة بعد.',
            default => $fallback !== '' ? $fallback : 'خطأ غير معروف من خادم فوترة.',
        };
    }

    protected function result(bool $ok, bool $changed, string $message, ?string $code): array
    {
        return ['ok' => $ok, 'changed' => $changed, 'message' => $message, 'code' => $code];
    }
}
