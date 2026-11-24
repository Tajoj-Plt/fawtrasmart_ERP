<?php

namespace App\Services\Zatca;

use App\Models\CreditNotification;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Invoice;
use App\Models\ZatcaDocument;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Saleh7\Zatca\Exceptions\ZatcaApiException;
use Saleh7\Zatca\ZatcaAPI;
use Throwable;

/**
 * إرسال مستندات المبيعات إلى هيئة الزكاة والضريبة والجمارك.
 *
 * ══════════ السلسلة ══════════
 *
 * كلّ مستندٍ يحمل عدّاداً (ICV) يزيد واحداً، وبصمةَ المستند الذي قبله
 * (PIH). فالتوقيع يجري **تحت قفل**: مستندان يُوقَّعان في اللحظة نفسها
 * يأخذان العدّاد نفسه والبصمة نفسها، وتنكسر السلسلة عند الهيئة. والإرسال
 * نفسه خارج القفل — نداءُ شبكةٍ قد يطول، ولا حاجة لحبس غيره به.
 *
 * ══════════ موقَّعٌ مرّةً، مُرسَلٌ مراراً ══════════
 *
 * يُحفَظ الملفّ الموقَّع قبل إرساله. فإن انقطع الاتصال أُعيد إرسال **الملفّ
 * نفسه** بعدّاده وبصمته، لا ملفٌّ جديد — وإلا أخذ المستند الواحد حلقتين في
 * السلسلة وبلغ الهيئةَ مرّتين إن كان الأوّل وصل ولم يصل ردُّه.
 *
 * أمّا ما **رفضته** الهيئة فلا يُعاد: يُصحَّح المستند ويُوقَّع من جديد بحلقةٍ
 * جديدة، والمرفوض يبقى في السجلّ لأنّ عدّاده استُهلك.
 *
 * Usage:
 *   $document = app(ZatcaInvoiceService::class)->submit($invoice);
 *   app(ZatcaInvoiceService::class)->submitQuietly($invoice);   // بعد الحفظ
 */
class ZatcaInvoiceService
{
    /** أقصى انتظارٍ لقفل السلسلة، بالثواني */
    private const LOCK_WAIT = 20;

    /** @var array<string, string|null> نتائج الإرسال في هذا الطلب */
    private static array $notices = [];

    /** مدّة إرجاء فاتورةٍ رفضت الدورةُ المجدولة إرسالها لنقصٍ في بياناتها */
    private const DEFER_HOURS = 6;

    public function __construct(
        protected ZatcaPayloadBuilder $payloads,
        protected ZatcaXmlFactory $xml,
        protected ZatcaOnboardingService $onboarding,
    ) {}

    /**
     * يوقّع المستند إن لم يُوقَّع، ويرسله.
     *
     * @throws ZatcaException حين يمتنع الإرسال لسببٍ يصلحه المستخدم
     */
    public function submit(Invoice|CreditNotification $source): ZatcaDocument
    {
        $settings = ElectronicInvoiceSetting::current();

        $this->assertCanSubmit($settings);

        [$type, $id] = $this->identify($source);

        $document = ZatcaDocument::latestFor($type, $id, $settings->environment);

        if ($document?->isAccepted()) {
            throw new ZatcaException(__('zatca.errors.already_sent'));
        }

        // مرفوضٌ ⇒ حلقةٌ جديدة. معلَّقٌ أو متعثّر ⇒ الملفّ نفسه
        if ($document === null || ! $document->canRetry()) {
            $document = $this->sign($source, $settings, $type, $id);
        }

        return $this->send($document, $settings);
    }

    /**
     * الإرسال بعد حفظ المستند — لا يُسقِط الحفظ مهما جرى.
     *
     * الفاتورة حُفظت وقُيِّدت؛ تعذُّر بلوغ الهيئة لا يُلغيها. يُسجَّل السبب
     * ويُعاد لتعرضه الشاشة تنبيهاً، والمهمّة المجدولة تعيد المحاولة.
     *
     * @return string|null رسالة التنبيه، أو `null` إن تمّ الإرسال أو لم يُطلَب
     */
    public function submitQuietly(Invoice|CreditNotification $source, string $trigger = 'create'): ?string
    {
        return self::$notices[$this->noticeKey($source)] = $this->attempt($source, $trigger);
    }

    /**
     * ما انتهى إليه إرسال هذا المستند في هذا الطلب — يُقرأ مرّة واحدة.
     *
     * المراقب يرسل بعد اكتمال الحفظ، والمتحكّم هو من يخاطب المستخدم. فيسأل
     * المتحكّم هنا عمّا جرى بدل أن يرسل هو أيضاً فيُرسَل المستند مرّتين.
     */
    public function pullNotice(Invoice|CreditNotification $source): ?string
    {
        $key = $this->noticeKey($source);
        $notice = self::$notices[$key] ?? null;

        unset(self::$notices[$key]);

        return $notice;
    }

    /**
     * `pullNotice()` بلا إنشاءٍ للخدمة — للمتحكّم بعد الحفظ.
     *
     * المستند حُفظ وقُيِّد قبل هذا النداء؛ فلو تعذّر إنشاء الخدمة نفسها
     * (حزمةٌ ناقصة، إعدادٌ معطوب) فلا يُقال للمستخدم «تعذّر الحفظ» عن حفظٍ
     * اكتمل — يُسجَّل السبب ويُعرَض تنبيهاً.
     */
    public static function noticeFor(Invoice|CreditNotification $source): ?string
    {
        try {
            return app(static::class)->pullNotice($source);
        } catch (Throwable $e) {
            Log::warning('ZATCA notice unavailable', ['message' => $e->getMessage()]);

            return __('zatca.errors.unexpected');
        }
    }

    protected function attempt(Invoice|CreditNotification $source, string $trigger): ?string
    {
        try {
            $settings = ElectronicInvoiceSetting::current();

            if (! $settings->canSubmit() || ! $this->triggers($settings, $source, $trigger)) {
                return null;
            }

            /* ما قبل التسجيل ليس من عهدة هذا الربط: فاتورةٌ قديمة يُسدَّد
               باقيها اليوم لا تُرسَل إلى الهيئة بتاريخٍ مضى عليه شهور. */
            if ($settings->onboarded_at !== null
                && $source->created_at !== null
                && $source->created_at->lt($settings->onboarded_at)) {
                return null;
            }

            [$type, $id] = $this->identify($source);

            if (ZatcaDocument::latestFor($type, $id, $settings->environment)?->isAccepted()) {
                return null;
            }

            $document = $this->submit($source);

            return $document->isAccepted() ? null : $this->summary($document);
        } catch (ZatcaException $e) {
            return $this->describe($e);
        } catch (Throwable $e) {
            Log::error('ZATCA submit failed', ['exception' => $e]);
            OperationLog::failure($e, 'zatca', 'submit', $source);

            return __('zatca.errors.unexpected');
        }
    }

    protected function noticeKey(Invoice|CreditNotification $source): string
    {
        return implode(':', $this->identify($source));
    }

    /** إعادة إرسال ملفٍّ موقَّع كما هو. */
    public function retry(ZatcaDocument $document): ZatcaDocument
    {
        $settings = ElectronicInvoiceSetting::current();

        $this->assertCanSubmit($settings);

        if (! $document->canRetry()) {
            throw new ZatcaException(__('zatca.errors.cannot_retry'));
        }

        if ($document->environment !== $settings->environment) {
            throw new ZatcaException(__('zatca.errors.other_environment'));
        }

        return $this->send($document, $settings);
    }

    /**
     * دورة المهمّة المجدولة.
     *
     * @return array{retried: int, sent: int, accepted: int, failed: int}
     */
    public function processPending(int $limit = 50, bool $dryRun = false): array
    {
        $stats = ['retried' => 0, 'sent' => 0, 'accepted' => 0, 'failed' => 0];

        $settings = ElectronicInvoiceSetting::current();

        if (! $settings->canSubmit()) {
            return $stats;
        }

        // ١. ما وُقِّع ولم يبلغ الهيئة — بترتيب السلسلة
        $pending = ZatcaDocument::query()
            ->where('environment', $settings->environment)
            ->where('status', ZatcaDocument::STATUS_PENDING)
            ->where('attempts', '<', ZatcaDocument::MAX_ATTEMPTS)
            ->orderBy('icv')
            ->limit($limit)
            ->get();

        foreach ($pending as $document) {
            $stats['retried']++;

            if ($dryRun) {
                continue;
            }

            try {
                $this->send($document, $settings)->isAccepted() ? $stats['accepted']++ : $stats['failed']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::warning('ZATCA retry failed', ['document' => $document->id, 'message' => $e->getMessage()]);
            }
        }

        // ٢. فواتير لم تُرسَل قطّ والإرسال التلقائي مفعَّل — منذ التسجيل فقط،
        // فما قبله ليس من عهدة هذا الربط
        if (! $settings->auto_send_after_create || $settings->onboarded_at === null) {
            return $stats;
        }

        $remaining = max(0, $limit - $pending->count());

        $invoices = Invoice::query()
            ->notVoided()
            ->where('created_at', '>=', $settings->onboarded_at)
            ->whereNotIn('id', ZatcaDocument::query()
                ->where('document_type', ZatcaDocument::TYPE_INVOICE)
                ->where('environment', $settings->environment)
                ->select('document_id'))
            ->when($settings->send_on_payment, fn ($q) => $q->where('payment_status', 1))
            ->orderBy('id')
            // أوسع من الحدّ: بعضها مُرجأ أدناه
            ->limit($remaining * 3)
            ->get();

        foreach ($invoices as $invoice) {
            if ($stats['sent'] >= $remaining) {
                break;
            }

            /* ما رُفض لنقصٍ في بياناته (عنوان عميل، بندٌ بلا تصنيف) لا
               يُصلحه تكرار المحاولة كلّ ربع ساعة: يُرجأ ساعاتٍ حتى يُصحَّح،
               وإلا ملأ السجلَّ وزاحم الفواتير السليمة على حدّ الدورة. */
            $deferKey = 'zatca:deferred:invoice:'.$invoice->id;

            if (Cache::has($deferKey)) {
                continue;
            }

            $stats['sent']++;

            if ($dryRun) {
                continue;
            }

            try {
                $this->submit($invoice)->isAccepted() ? $stats['accepted']++ : $stats['failed']++;
            } catch (ZatcaException $e) {
                $stats['failed']++;
                Cache::put($deferKey, $this->describe($e), now()->addHours(self::DEFER_HOURS));
                Log::info('ZATCA scheduled submit deferred', ['invoice' => $invoice->id, 'reason' => $this->describe($e)]);
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::warning('ZATCA scheduled submit failed', ['invoice' => $invoice->id, 'message' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /** رسالة الخطأ بتفاصيلها في سطرٍ واحد — للتنبيهات. */
    public function describe(ZatcaException $e): string
    {
        $details = $e->details();

        return $details === []
            ? $e->getMessage()
            : $e->getMessage().' — '.implode(' • ', array_slice($details, 0, 5));
    }

    /** خلاصة حالة مستندٍ لم يُقبَل. */
    public function summary(ZatcaDocument $document): string
    {
        $details = implode(' • ', array_slice((array) $document->errors, 0, 5)) ?: (string) $document->last_error;

        return __('zatca.status_message.'.$document->status).($details !== '' ? ' — '.$details : '');
    }

    protected function assertCanSubmit(ElectronicInvoiceSetting $settings): void
    {
        ZatcaGuard::assertPackageInstalled();

        if (! $settings->is_enabled) {
            throw new ZatcaException(__('zatca.errors.disabled'));
        }

        if (! $settings->isPhaseTwo()) {
            throw new ZatcaException(__('zatca.errors.phase_one'));
        }

        if (! $settings->isComplete()) {
            throw new ZatcaException(__('zatca.errors.settings_incomplete'));
        }

        if (! $settings->hasCredentials()) {
            throw new ZatcaException(__('zatca.errors.not_onboarded'));
        }

        if ($settings->certificateExpired()) {
            throw new ZatcaException(__('zatca.errors.certificate_expired'));
        }
    }

    /** أيُرسَل هذا المستند عند هذا الحدث بحسب «سلوك الإرسال»؟ */
    protected function triggers(ElectronicInvoiceSetting $settings, Invoice|CreditNotification $source, string $trigger): bool
    {
        if (! $settings->auto_send_after_create) {
            return false;
        }

        if (! $settings->send_on_payment || ! $source instanceof Invoice) {
            return $trigger === 'create';
        }

        // مؤجَّلٌ حتى السداد: يُرسَل عند الإنشاء إن وُلد مسدَّداً، وعند الدفع
        return (int) $source->payment_status === 1;
    }

    /** @return array{0: string, 1: int} */
    protected function identify(Model $source): array
    {
        return [
            $source instanceof CreditNotification ? ZatcaDocument::TYPE_CREDIT_NOTE : ZatcaDocument::TYPE_INVOICE,
            (int) $source->getKey(),
        ];
    }

    /** يبني الملفّ ويوقّعه ويحجز له حلقته في السلسلة. */
    protected function sign(Invoice|CreditNotification $source, ElectronicInvoiceSetting $settings, string $type, int $id): ZatcaDocument
    {
        // يُبنى قبل القفل: رفضُ البيانات الناقصة لا يستهلك عدّاداً
        $payload = $source instanceof CreditNotification
            ? $this->payloads->forCreditNote($source, $settings)
            : $this->payloads->forInvoice($source, $settings);

        $certificate = $this->xml->certificate($settings);

        $lock = Cache::lock('zatca:chain:'.$settings->environment, 60);

        try {
            $lock->block(self::LOCK_WAIT);
        } catch (Throwable $e) {
            throw new ZatcaException(__('zatca.errors.busy'));
        }

        try {
            return DB::transaction(function () use ($payload, $certificate, $settings, $type, $id) {
                $last = ZatcaDocument::query()
                    ->where('environment', $settings->environment)
                    ->orderByDesc('icv')
                    ->lockForUpdate()
                    ->first();

                $icv = (int) ($last->icv ?? 0) + 1;
                $previousHash = $last->invoice_hash ?? ZatcaXmlFactory::FIRST_PREVIOUS_HASH;
                $uuid = (string) Str::uuid();

                $signed = $this->xml->sign($payload['data'], $payload['lines'], $uuid, $icv, $previousHash, $certificate);

                return ZatcaDocument::create([
                    'document_type' => $type,
                    'document_id' => $id,
                    'document_number' => $payload['number'],
                    'environment' => $settings->environment,
                    'profile' => $payload['profile'],
                    'kind' => $payload['kind'],
                    'uuid' => $uuid,
                    'icv' => $icv,
                    'invoice_hash' => $signed['hash'],
                    'previous_hash' => $previousHash,
                    'qr' => $signed['qr'],
                    'signed_xml' => $signed['xml'],
                    'status' => ZatcaDocument::STATUS_PENDING,
                    'created_by' => Auth::id(),
                ]);
            });
        } finally {
            $lock->release();
        }
    }

    /** نداء الهيئة وتسجيل ردّها. */
    protected function send(ZatcaDocument $document, ElectronicInvoiceSetting $settings): ZatcaDocument
    {
        $api = new ZatcaAPI(ZatcaOnboardingService::apiEnvironment($settings->environment));

        $arguments = [
            (string) $settings->production_certificate,
            (string) $settings->production_secret,
            (string) $document->signed_xml,
            (string) $document->invoice_hash,
            (string) $document->uuid,
        ];

        $document->attempts++;
        $document->last_attempt_at = now();

        try {
            if ($document->profile === 'standard') {
                $response = $api->submitClearanceInvoice(...$arguments);
                $cleared = $response->getDecodedClearedInvoice();

                $document->fill([
                    'status' => ZatcaDocument::STATUS_CLEARED,
                    'cleared_xml' => $cleared,
                    // رمز الفاتورة الضريبية هو ما ختمته الهيئة
                    'qr' => ($cleared ? $this->xml->qrFrom($cleared) : null) ?: $document->qr,
                ]);
            } else {
                $response = $api->submitReportingInvoice(...$arguments);

                $document->status = ZatcaDocument::STATUS_REPORTED;
            }

            $document->fill([
                'http_status' => $response->getStatusCode(),
                'warnings' => ZatcaOnboardingService::messages($response->getWarningMessages()) ?: null,
                'errors' => null,
                'last_error' => null,
                'submitted_at' => now(),
            ])->save();

            OperationLog::record('sent', 'zatca', $document->document_id, __('zatca.log.sent', [
                'number' => $document->document_number,
                'status' => __('zatca.status.'.$document->status),
            ]));
        } catch (ZatcaApiException $e) {
            $this->recordFailure($document, $e);
        }

        return $document;
    }

    /**
     * الرفض غير التعثّر.
     *
     * 400 ⇒ الهيئة قرأت المستند ورفضته: نهائيّ، وإعادة إرساله لا تغيّر شيئاً.
     * 409 ⇒ المستند عندها أصلاً: وصل في محاولةٍ سابقة وضاع ردُّها.
     * غير ذلك (انقطاع، 401، 5xx) ⇒ لم يُقرأ: يبقى معلَّقاً ويُعاد.
     */
    protected function recordFailure(ZatcaDocument $document, ZatcaApiException $e): void
    {
        $context = $e->getContext();
        $status = (int) ($context['statusCode'] ?? 0);
        $response = is_array($context['response'] ?? null) ? $context['response'] : [];

        $errors = ZatcaOnboardingService::messages($response['validationResults']['errorMessages'] ?? []);
        $warnings = ZatcaOnboardingService::messages($response['validationResults']['warningMessages'] ?? []);

        if ($status === 409) {
            $document->fill([
                'status' => $document->profile === 'standard' ? ZatcaDocument::STATUS_CLEARED : ZatcaDocument::STATUS_REPORTED,
                'http_status' => $status,
                'submitted_at' => now(),
                'last_error' => null,
            ])->save();

            return;
        }

        $explained = $this->onboarding->explain($e);

        $document->fill([
            'http_status' => $status ?: null,
            'warnings' => $warnings ?: null,
            'errors' => $errors ?: ($explained->details() ?: null),
            'last_error' => mb_substr($explained->getMessage(), 0, 2000),
        ]);

        if ($status === 400) {
            $document->status = ZatcaDocument::STATUS_REJECTED;
        } elseif ($document->attempts >= ZatcaDocument::MAX_ATTEMPTS) {
            $document->status = ZatcaDocument::STATUS_FAILED;
        }

        $document->save();

        OperationLog::failure(
            new ZatcaException($document->document_number.': '.$explained->getMessage()),
            'zatca',
            'submit',
            $document->document_id
        );
    }
}
