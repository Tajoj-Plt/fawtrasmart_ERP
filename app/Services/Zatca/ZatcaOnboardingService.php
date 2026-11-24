<?php

namespace App\Services\Zatca;

use App\Models\ElectronicInvoiceSetting;
use App\Support\OperationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionProperty;
use Saleh7\Zatca\CertificateBuilder;
use Saleh7\Zatca\Exceptions\CertificateBuilderException;
use Saleh7\Zatca\Exceptions\ZatcaApiException;
use Saleh7\Zatca\ZatcaAPI;
use Throwable;

/**
 * تسجيل النظام لدى هيئة الزكاة والضريبة والجمارك.
 *
 * الرحلة أربع خطوات، وكلّها في نداءٍ واحد لأنّ رمز OTP يصلح ساعةً واحدة
 * ولمحاولةٍ واحدة:
 *
 *   ١. مفتاحٌ خاصّ وطلب توقيع (CSR) ببيانات المنشأة.
 *   ٢. شهادة امتثال مقابل CSR ورمز OTP من بوابة «فاتورة».
 *   ٣. فحوص الامتثال: مستندٌ من كلّ نوعٍ تصدره المنشأة، موقَّعاً.
 *   ٤. شهادة الإنتاج — وبها يبدأ الإرسال.
 *
 * **لا يُحفَظ شيء قبل الخطوة الرابعة.** المفتاح والشهادة زوجٌ واحد: حفظُ
 * مفتاحٍ جديد مع بقاء شهادةٍ قديمة يُخرج توقيعاً لا تقبله الهيئة، ويقطع
 * إرسالاً كان يعمل. ففشلُ أيّ خطوةٍ يترك المحفوظ كما كان.
 *
 * Usage:
 *   $result = app(ZatcaOnboardingService::class)->onboard($settings, $otp);
 */
class ZatcaOnboardingService
{
    /**
     * بيئتنا ← (بيئة واجهة الهيئة، بيئة قالب الشهادة).
     *
     * القيم نصّاً لا `CertificateBuilder::ENV_*`: PHP يقيّم ثوابت الصنف عند
     * أوّل إنشاءٍ له، فثابتٌ يشير إلى صنفٍ من الحزمة يُسقط `new` كلّه على
     * خادمٍ لم تُثبَّت عليه الحزمة — وهذه الخدمة تُحقَن في `ZatcaInvoiceService`
     * الذي يُنشأ بعد حفظ كلّ فاتورة. القيم ثابتةٌ في الحزمة ومطابقةٌ لها.
     */
    private const ENVIRONMENTS = [
        ElectronicInvoiceSetting::ENV_SANDBOX => ['sandbox', 'nonprod'],
        ElectronicInvoiceSetting::ENV_SIMULATION => ['simulation', 'simulation'],
        ElectronicInvoiceSetting::ENV_PRODUCTION => ['production', 'production'],
    ];

    /** ما ترفضه الهيئة في حقول الشهادة */
    private const FORBIDDEN = '/[!@#$%&*_<=]/u';

    public function __construct(
        protected ZatcaPayloadBuilder $payloads,
        protected ZatcaXmlFactory $xml,
    ) {}

    /** اسم البيئة كما تعرفه واجهة الهيئة. */
    public static function apiEnvironment(string $environment): string
    {
        return self::ENVIRONMENTS[$environment][0] ?? 'sandbox';
    }

    /**
     * @return array{checks: array<int, array{label: string, status: string}>, expires_at: ?Carbon}
     */
    public function onboard(ElectronicInvoiceSetting $settings, string $otp): array
    {
        if (! $settings->isComplete()) {
            throw new ZatcaException(__('zatca.errors.settings_incomplete'));
        }

        ZatcaGuard::assertPackageInstalled();

        [$apiEnvironment, $csrEnvironment] = self::ENVIRONMENTS[$settings->environment]
            ?? self::ENVIRONMENTS[ElectronicInvoiceSetting::ENV_SANDBOX];

        // تُفحَص بيانات البائع قبل استهلاك رمز OTP
        $this->payloads->supplier($settings);

        $serial = $settings->egs_serial ?: (string) Str::uuid();

        try {
            [$csr, $privateKey] = $this->generateCsr($settings, $serial, $csrEnvironment);

            $api = new ZatcaAPI($apiEnvironment);
            $compliance = $api->requestComplianceCertificate($csr, trim($otp));

            // نسخةٌ في الذاكرة تحمل شهادة الامتثال للتوقيع — لا تُحفَظ
            $draft = (clone $settings)->forceFill([
                'private_key' => $privateKey,
                'compliance_certificate' => $compliance->getCertificate(),
                'compliance_secret' => $compliance->getSecret(),
            ]);

            $checks = $this->runComplianceChecks($api, $draft);

            $production = $api->requestProductionCertificate(
                $compliance->getCertificate(),
                $compliance->getSecret(),
                $compliance->getRequestId()
            );
        } catch (ZatcaApiException $e) {
            throw $this->explain($e);
        } catch (CertificateBuilderException $e) {
            throw new ZatcaException(__('zatca.errors.csr_failed', ['reason' => $e->getMessage()]));
        }

        $expiresAt = $this->expiryOf($production->getCertificate());

        $settings->forceFill([
            'egs_serial' => $serial,
            'credentials_environment' => $settings->environment,
            'private_key' => $privateKey,
            'csr' => $csr,
            'compliance_certificate' => $compliance->getCertificate(),
            'compliance_secret' => $compliance->getSecret(),
            'compliance_request_id' => $compliance->getRequestId(),
            'production_certificate' => $production->getCertificate(),
            'production_secret' => $production->getSecret(),
            'production_request_id' => $production->getRequestId(),
            'certificate_expires_at' => $expiresAt,
            'onboarded_at' => now(),
            'last_checked_at' => now(),
            'last_check_status' => 'success',
            'last_check_message' => null,
        ])->save();

        $this->log('success', __('zatca.log.onboarded', [
            'env' => __('sales_settings.electronic.env.'.$settings->environment),
        ]));

        return ['checks' => $checks, 'expires_at' => $expiresAt];
    }

    /** تسجيل فشل المحاولة في الإعدادات — تعرضه اللوحة. */
    public function recordFailure(ElectronicInvoiceSetting $settings, string $message): void
    {
        $settings->forceFill([
            'last_checked_at' => now(),
            'last_check_status' => 'failed',
            'last_check_message' => mb_substr($message, 0, 2000),
        ])->save();

        $this->log('failure', $message);
    }

    /**
     * @return array{0: string, 1: string} طلب التوقيع والمفتاح الخاصّ (PEM)
     */
    protected function generateCsr(ElectronicInvoiceSetting $settings, string $serial, string $csrEnvironment): array
    {
        $tradeName = $this->clean((string) $settings->fromEstablishment('trade_name'));
        $taxNumber = preg_replace('/\D+/', '', (string) $settings->fromEstablishment('tax_number'));

        $builder = (new CertificateBuilder)
            ->setOrganizationIdentifier((string) $taxNumber)
            ->setSerialNumber('Fawtra', 'ERP', $serial)
            ->setCommonName($this->clean((string) ($settings->common_name ?: $tradeName)))
            ->setCountryName('SA')
            ->setOrganizationName($tradeName)
            ->setOrganizationalUnitName($this->clean((string) $settings->tax_group_name))
            ->setAddress($this->clean(trim($settings->district.' '.$settings->fromEstablishment('city'))))
            ->setInvoiceType(str_pad((string) $settings->invoice_types, 4, '0'))
            ->setEnvironment($csrEnvironment)
            ->setBusinessCategory($this->clean((string) $settings->business_category));

        $builder->generate();

        return [$builder->getCsr(), $this->exportPrivateKey($builder)];
    }

    /**
     * المفتاح الخاصّ نصّاً.
     *
     * الحزمة تصدّره إلى ملفٍّ بلا ملفّ إعدادات، و`openssl` على ويندوز
     * (XAMPP) لا يجد `openssl.cnf` الافتراضي فيفشل التصدير — والمفتاح
     * نفسه وُلِّد سليماً. وهو لا يُكتَب على القرص أصلاً هنا: يُحفَظ معمّى في
     * القاعدة. فيُقرأ من الكائن ويُصدَّر بملفّ إعداداتٍ أدنى.
     */
    protected function exportPrivateKey(CertificateBuilder $builder): string
    {
        $property = new ReflectionProperty($builder, 'privateKey');
        $property->setAccessible(true);
        $key = $property->getValue($builder);

        $config = tempnam(sys_get_temp_dir(), 'zatca_');
        file_put_contents($config, "[req]\ndistinguished_name = req_dn\n[req_dn]\n");

        try {
            if (! openssl_pkey_export($key, $pem, null, ['config' => $config])) {
                throw new ZatcaException(__('zatca.errors.csr_failed', ['reason' => (string) openssl_error_string()]));
            }
        } finally {
            @unlink($config);
        }

        return $pem;
    }

    /**
     * مستندٌ من كلّ نوعٍ تصدره المنشأة. أيّ رفضٍ يوقف التسجيل.
     *
     * @return array<int, array{label: string, status: string}>
     */
    protected function runComplianceChecks(ZatcaAPI $api, ElectronicInvoiceSetting $draft): array
    {
        $certificate = $this->xml->certificate($draft, 'compliance');

        $results = [];
        $previousHash = ZatcaXmlFactory::FIRST_PREVIOUS_HASH;
        $sequence = 0;

        foreach (['standard', 'simplified'] as $profile) {
            if (! $draft->issues($profile)) {
                continue;
            }

            foreach (['invoice', 'credit', 'debit'] as $kind) {
                $sequence++;
                $uuid = (string) Str::uuid();
                $label = __('zatca.profile.'.$profile).' — '.__('zatca.kind.'.$kind);

                $signed = $this->xml->sign(
                    $this->payloads->sample($draft, $profile, $kind, $sequence),
                    [],
                    $uuid,
                    $sequence,
                    $previousHash,
                    $certificate
                );

                try {
                    $response = $api->validateInvoiceCompliance(
                        (string) $draft->compliance_certificate,
                        (string) $draft->compliance_secret,
                        $signed['xml'],
                        $signed['hash'],
                        $uuid
                    );
                } catch (ZatcaApiException $e) {
                    throw ZatcaException::withDetails(
                        __('zatca.errors.compliance_failed', ['document' => $label]),
                        self::messages($e->getContext()['response']['validationResults']['errorMessages'] ?? [])
                            ?: [$this->explain($e)->getMessage()]
                    );
                }

                if ($response->hasErrors()) {
                    throw ZatcaException::withDetails(
                        __('zatca.errors.compliance_failed', ['document' => $label]),
                        self::messages($response->getErrorMessages())
                    );
                }

                $results[] = ['label' => $label, 'status' => (string) ($response->getValidationStatus() ?? 'PASS')];
                $previousHash = $signed['hash'];
            }
        }

        if ($results === []) {
            throw new ZatcaException(__('zatca.errors.no_invoice_types'));
        }

        return $results;
    }

    /** تاريخ انتهاء الشهادة — يُقرأ منها لا يُفترَض. */
    protected function expiryOf(string $certificate): ?Carbon
    {
        try {
            $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(trim($certificate), 64, "\n").'-----END CERTIFICATE-----';
            $parsed = openssl_x509_parse($pem);

            return isset($parsed['validTo_time_t']) ? Carbon::createFromTimestamp($parsed['validTo_time_t']) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** خطأ الهيئة بلغةٍ يفهمها من يضغط الزرّ. */
    public function explain(ZatcaApiException $e): ZatcaException
    {
        $context = $e->getContext();
        $status = (int) ($context['statusCode'] ?? 0);
        $response = $context['response'] ?? [];

        Log::warning('ZATCA API error', ['status' => $status, 'endpoint' => $context['endpoint'] ?? null, 'response' => $response, 'message' => $context['message'] ?? $e->getMessage()]);

        $details = self::messages(
            $response['validationResults']['errorMessages'] ?? $response['errors'] ?? []
        );

        if ($details === [] && is_string($response['message'] ?? null)) {
            $details[] = $response['message'];
        }

        $message = match (true) {
            $status === 0 => __('zatca.errors.unreachable'),
            $status === 400 && str_contains(json_encode($response), 'OTP') => __('zatca.errors.invalid_otp'),
            $status === 401 => __('zatca.errors.unauthorized'),
            $status >= 500 => __('zatca.errors.server', ['status' => $status]),
            default => __('zatca.errors.api', ['status' => $status]),
        };

        return ZatcaException::withDetails($message, $details);
    }

    /**
     * رسائل الهيئة نصوصاً: «الرمز: الرسالة».
     *
     * @param  mixed  $messages
     * @return array<int, string>
     */
    public static function messages($messages): array
    {
        if (! is_array($messages)) {
            return [];
        }

        $lines = [];

        foreach ($messages as $message) {
            if (is_string($message)) {
                $lines[] = $message;

                continue;
            }

            if (is_array($message)) {
                $text = trim((string) ($message['message'] ?? ''));
                $code = trim((string) ($message['code'] ?? ''));

                if ($text !== '' || $code !== '') {
                    $lines[] = trim($code.($code !== '' && $text !== '' ? ': ' : '').$text);
                }
            }
        }

        return array_values(array_unique($lines));
    }

    protected function clean(string $value): string
    {
        return trim((string) preg_replace(self::FORBIDDEN, ' ', $value));
    }

    protected function log(string $outcome, string $message): void
    {
        $outcome === 'success'
            ? OperationLog::record('onboarded', 'zatca', null, $message)
            : OperationLog::failure(new ZatcaException($message), 'zatca', 'onboard');
    }
}
